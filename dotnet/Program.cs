using GlobalPayments.Api;
using GlobalPayments.Api.Entities;
using GlobalPayments.Api.Entities.Reporting;
using GlobalPayments.Api.Services;
using dotenv.net;
using System.Text.Json;
using GpEnvironment = GlobalPayments.Api.Entities.Environment;

DotEnv.Load();

var builder = WebApplication.CreateBuilder(args);
var app = builder.Build();

app.Use(async (context, next) =>
{
    context.Response.Headers.Append("Access-Control-Allow-Origin", "*");
    context.Response.Headers.Append("Access-Control-Allow-Methods", "GET, POST, OPTIONS");
    context.Response.Headers.Append("Access-Control-Allow-Headers", "Content-Type");
    if (context.Request.Method == "OPTIONS") { context.Response.StatusCode = 200; return; }
    await next();
});

app.UseDefaultFiles();
app.UseStaticFiles();

ConfigureSdk();

var isSandbox = !(System.Environment.GetEnvironmentVariable("GP_ENVIRONMENT") ?? "sandbox")
    .Equals("production", StringComparison.OrdinalIgnoreCase);
const string sandboxNote = "Write operations are simulated in sandbox and not submitted to the card network. This action would execute against a live dispute in production.";

var port = System.Environment.GetEnvironmentVariable("PORT") ?? "8000";
app.Urls.Add($"http://0.0.0.0:{port}");

// GET /config
app.MapGet("/config", () => Results.Json(new
{
    environment = System.Environment.GetEnvironmentVariable("GP_ENVIRONMENT") ?? "sandbox",
    backend = "dotnet"
}));

// GET /disputes
app.MapGet("/disputes", (HttpRequest req) =>
{
    try
    {
        var page     = Math.Max(1, int.TryParse(req.Query["page"], out var p) ? p : 1);
        var pageSize = Math.Min(50, Math.Max(1, int.TryParse(req.Query["pageSize"], out var ps) ? ps : 10));
        var status    = req.Query["status"].FirstOrDefault();
        var stage     = req.Query["stage"].FirstOrDefault();
        var startStr  = req.Query["startDate"].FirstOrDefault();
        var endStr    = req.Query["endDate"].FirstOrDefault();

        var start = startStr != null ? DateTime.Parse(startStr) : DateTime.UtcNow.AddDays(-90);

        var query = ReportingService.FindDisputesPaged(page, pageSize)
            .OrderBy(DisputeSortProperty.Id, SortDirection.Descending)
            .Where(DataServiceCriteria.StartStageDate, start);

        if (endStr != null)  query = query.And(DataServiceCriteria.EndStageDate, DateTime.Parse(endStr));
        if (status != null)  query = query.And(SearchCriteria.DisputeStatus, status);
        if (stage != null)   query = query.And(SearchCriteria.DisputeStage, stage);

        var result = query.Execute();

        var disputes = result.Results?.Select(FormatDispute).ToList() ?? [];
        return Results.Json(new
        {
            disputes,
            totalCount  = result.TotalRecordCount,
            totalPages  = (int)Math.Ceiling((double)result.TotalRecordCount / pageSize),
            currentPage = page
        });
    }
    catch (Exception ex)
    {
        return Results.Json(new { error = new { code = "500", message = ex.Message } }, statusCode: 500);
    }
});

// GET /disputes/{id}
app.MapGet("/disputes/{id}", (string id) =>
{
    try
    {
        var d = ReportingService.DisputeDetail(id).Execute();
        return Results.Json(new { dispute = new
        {
            caseId                     = d.CaseId,
            caseStatus                 = d.CaseStatus,
            caseStage                  = d.CaseStage,
            caseAmount                 = d.CaseAmount,
            caseCurrency               = NormalizeCurrency(d.CaseCurrency),
            transactionCardType        = d.TransactionCardType,
            transactionMaskedCardNumber = d.TransactionMaskedCardNumber,
            transactionARN             = d.TransactionARN,
            respondByDate              = d.RespondByDate,
            depositDate                = d.DepositDate,
            caseIdTime                 = d.CaseIdTime,
            merchantHierarchy          = d.MerchantHierarchy,
        }});
    }
    catch (Exception ex)
    {
        return Results.Json(new { error = new { code = "500", message = ex.Message } }, statusCode: 500);
    }
});

// POST /disputes/{id}/accept
app.MapPost("/disputes/{id}/accept", (string id) =>
{
    if (isSandbox) return Results.Json(new { success = true, responseCode = "SUCCESS", sandbox = true, note = sandboxNote });
    try
    {
        var dispute = new DisputeSummary { CaseId = id };
        var response = dispute.Accept().Execute();
        return Results.Json(new { success = true, responseCode = response.ResponseCode });
    }
    catch (Exception ex)
    {
        return Results.Json(new { error = new { code = "500", message = ex.Message } }, statusCode: 500);
    }
});

// POST /disputes/{id}/challenge
app.MapPost("/disputes/{id}/challenge", async (string id, HttpRequest req) =>
{
    if (isSandbox) return Results.Json(new { success = true, responseCode = "SUCCESS", sandbox = true, note = sandboxNote });
    try
    {
        var body = await JsonSerializer.DeserializeAsync<ChallengeRequest>(req.Body,
            new JsonSerializerOptions { PropertyNameCaseInsensitive = true });

        if (body?.Documents == null || body.Documents.Count == 0)
            return Results.Json(new { error = new { code = "400", message = "At least one document is required" } }, statusCode: 400);

        var documents = body.Documents.Select(d => new DisputeDocument
        {
            Type          = d.Type,
            Base64Content = d.B64Content,
        }).ToList();

        var dispute = new DisputeSummary { CaseId = id };
        var response = dispute.Challenge(documents).Execute();
        return Results.Json(new { success = true, responseCode = response.ResponseCode });
    }
    catch (Exception ex)
    {
        return Results.Json(new { error = new { code = "500", message = ex.Message } }, statusCode: 500);
    }
});

app.Run();

static string? NormalizeCurrency(string? code)
{
    if (code is null) return null;
    var map = new Dictionary<string, string> {
        {"MX","MXN"}, {"GB","GBP"}, {"AU","AUD"}, {"CA","CAD"}, {"JP","JPY"}, {"EU","EUR"},
        {"SG","SGD"}, {"HK","HKD"}, {"NZ","NZD"}, {"ZA","ZAR"}, {"BR","BRL"}, {"IN","INR"},
        {"CN","CNY"}, {"SE","SEK"}, {"NO","NOK"}, {"DK","DKK"}, {"CH","CHF"}, {"AE","AED"},
    };
    return map.TryGetValue(code, out var v) ? v : code;
}

static object FormatDispute(DisputeSummary d) => new
{
    caseId                     = d.CaseId,
    caseStatus                 = d.CaseStatus,
    caseStage                  = d.CaseStage,
    caseAmount                 = d.CaseAmount,
    caseCurrency               = NormalizeCurrency(d.CaseCurrency),
    transactionCardType        = d.TransactionCardType,
    transactionMaskedCardNumber = d.TransactionMaskedCardNumber,
    transactionARN             = d.TransactionARN,
    respondByDate              = d.RespondByDate,
    depositDate                = d.DepositDate,
    caseIdTime                 = d.CaseIdTime,
};

static void ConfigureSdk()
{
    var config = new GpApiConfig
    {
        AppId      = System.Environment.GetEnvironmentVariable("GP_APP_ID"),
        AppKey     = System.Environment.GetEnvironmentVariable("GP_APP_KEY"),
        Channel    = Channel.CardNotPresent,
        Environment = (System.Environment.GetEnvironmentVariable("GP_ENVIRONMENT") ?? "sandbox")
                        .Equals("production", StringComparison.OrdinalIgnoreCase)
                        ? GpEnvironment.PRODUCTION
                        : GpEnvironment.TEST,
    };
    ServicesContainer.ConfigureService(config);
}

record DocumentRequest(string? Type, string B64Content);
record ChallengeRequest(List<DocumentRequest> Documents);
