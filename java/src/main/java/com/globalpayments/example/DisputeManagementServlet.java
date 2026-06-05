package com.globalpayments.example;

import com.global.api.ServicesContainer;
import com.global.api.entities.DisputeDocument;
import com.global.api.entities.Transaction;
import com.global.api.entities.enums.Channel;
import com.global.api.entities.enums.Environment;
import com.global.api.entities.enums.SortDirection;
import com.global.api.entities.enums.DisputeSortProperty;
import com.global.api.entities.exceptions.ApiException;
import com.global.api.entities.exceptions.ConfigurationException;
import com.global.api.entities.reporting.DataServiceCriteria;
import com.global.api.entities.reporting.DisputeSummary;
import com.global.api.entities.reporting.DisputeSummaryPaged;
import com.global.api.entities.reporting.SearchCriteria;
import com.global.api.serviceConfigs.GpApiConfig;
import com.global.api.services.ReportingService;
import com.google.gson.Gson;
import com.google.gson.JsonArray;
import com.google.gson.JsonObject;
import com.google.gson.JsonParser;
import io.github.cdimascio.dotenv.Dotenv;
import jakarta.servlet.ServletException;
import jakarta.servlet.annotation.WebServlet;
import jakarta.servlet.http.HttpServlet;
import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;

import java.io.IOException;
import java.io.PrintWriter;
import java.math.BigDecimal;
import java.text.SimpleDateFormat;
import java.time.LocalDate;
import java.time.ZoneId;
import java.util.ArrayList;
import java.util.Date;
import java.util.Map;
import java.util.stream.Collectors;

@WebServlet(urlPatterns = {"/config", "/disputes", "/disputes/*"})
public class DisputeManagementServlet extends HttpServlet {

    private static final String SANDBOX_NOTE = "Write operations are simulated in sandbox and not submitted to the card network. This action would execute against a live dispute in production.";
    private final Gson gson = new Gson();
    private boolean isSandbox;

    @Override
    public void init() throws ServletException {
        try {
            Dotenv dotenv = Dotenv.load();
            GpApiConfig config = new GpApiConfig();
            config.setAppId(dotenv.get("GP_APP_ID"));
            config.setAppKey(dotenv.get("GP_APP_KEY"));
            config.setChannel(Channel.CardNotPresent);
            String env = dotenv.get("GP_ENVIRONMENT", "sandbox");
            config.setEnvironment("production".equalsIgnoreCase(env) ? Environment.PRODUCTION : Environment.TEST);
            isSandbox = !"production".equalsIgnoreCase(env);
            ServicesContainer.configureService(config);
        } catch (ConfigurationException e) {
            throw new ServletException("Failed to configure GP-API SDK", e);
        }
    }

    @Override
    protected void doOptions(HttpServletRequest req, HttpServletResponse res) throws IOException {
        setCors(res);
        res.setStatus(200);
    }

    @Override
    protected void doGet(HttpServletRequest req, HttpServletResponse res) throws IOException {
        setCors(res);
        res.setContentType("application/json;charset=UTF-8");
        String path = req.getServletPath() + (req.getPathInfo() != null ? req.getPathInfo() : "");

        try (PrintWriter out = res.getWriter()) {
            if ("/config".equals(path)) {
                out.print(gson.toJson(new ConfigResponse(isSandbox ? "sandbox" : "production", "java")));
                return;
            }

            if ("/disputes".equals(path)) {
                handleListDisputes(req, out);
                return;
            }

            // /disputes/{id}
            if (path.matches("/disputes/[^/]+")) {
                String id = path.substring("/disputes/".length());
                handleDisputeDetail(id, out);
                return;
            }

            res.setStatus(404);
            out.print("{\"error\":{\"code\":\"404\",\"message\":\"Not found\"}}");
        }
    }

    @Override
    protected void doPost(HttpServletRequest req, HttpServletResponse res) throws IOException {
        setCors(res);
        res.setContentType("application/json;charset=UTF-8");
        String path = req.getServletPath() + (req.getPathInfo() != null ? req.getPathInfo() : "");

        try (PrintWriter out = res.getWriter()) {
            // /disputes/{id}/accept
            if (path.matches("/disputes/[^/]+/accept")) {
                String id = extractId(path);
                handleAccept(id, out);
                return;
            }

            // /disputes/{id}/challenge
            if (path.matches("/disputes/[^/]+/challenge")) {
                String id = extractId(path);
                String body = req.getReader().lines().collect(Collectors.joining());
                handleChallenge(id, body, out);
                return;
            }

            res.setStatus(404);
            out.print("{\"error\":{\"code\":\"404\",\"message\":\"Not found\"}}");
        }
    }

    private void handleListDisputes(HttpServletRequest req, PrintWriter out) throws IOException {
        try {
            int page     = Math.max(1, intParam(req, "page", 1));
            int pageSize = Math.min(50, Math.max(1, intParam(req, "pageSize", 10)));
            String status    = req.getParameter("status");
            String stage     = req.getParameter("stage");
            String startStr  = req.getParameter("startDate");
            String endStr    = req.getParameter("endDate");

            Date start = startStr != null
                ? new SimpleDateFormat("yyyy-MM-dd").parse(startStr)
                : Date.from(LocalDate.now().minusDays(90).atStartOfDay(ZoneId.systemDefault()).toInstant());

            var query = ReportingService.findDisputesPaged(page, pageSize)
                .orderBy(DisputeSortProperty.Id, SortDirection.Descending)
                .where(DataServiceCriteria.StartStageDate, start);

            if (endStr != null) {
                query = query.and(DataServiceCriteria.EndStageDate, new SimpleDateFormat("yyyy-MM-dd").parse(endStr));
            }
            if (status != null && !status.isEmpty()) query = query.and(SearchCriteria.DisputeStatus, status);
            if (stage  != null && !stage.isEmpty())  query = query.and(SearchCriteria.DisputeStage, stage);

            DisputeSummaryPaged result = query.execute();

            JsonArray disputes = new JsonArray();
            for (DisputeSummary d : result.getResults()) {
                disputes.add(formatDispute(d));
            }

            JsonObject response = new JsonObject();
            response.add("disputes", disputes);
            int totalPages = pageSize > 0 ? (int) Math.ceil((double) result.getTotalRecordCount() / pageSize) : 1;
            response.addProperty("totalCount",  result.getTotalRecordCount());
            response.addProperty("totalPages",  totalPages);
            response.addProperty("currentPage", page);
            out.print(gson.toJson(response));
        } catch (Exception e) {
            out.print(errorJson(e));
        }
    }

    private void handleDisputeDetail(String id, PrintWriter out) {
        try {
            DisputeSummary d = ReportingService.disputeDetail(id).execute();
            JsonObject wrap = new JsonObject();
            JsonObject detail = formatDispute(d);
            detail.addProperty("merchantHierarchy", d.getMerchantHierarchy());
            wrap.add("dispute", detail);
            out.print(gson.toJson(wrap));
        } catch (ApiException e) {
            out.print(errorJson(e));
        }
    }

    private void handleAccept(String id, PrintWriter out) {
        if (isSandbox) { out.print(sandboxMockJson()); return; }
        try {
            DisputeSummary dispute = new DisputeSummary();
            dispute.setCaseId(id);
            Transaction response = dispute.accept().execute();
            JsonObject json = new JsonObject();
            json.addProperty("success", true);
            json.addProperty("responseCode", response.getResponseCode());
            out.print(gson.toJson(json));
        } catch (ApiException e) {
            out.print(errorJson(e));
        }
    }

    private void handleChallenge(String id, String body, PrintWriter out) {
        if (isSandbox) { out.print(sandboxMockJson()); return; }
        try {
            JsonObject parsed = JsonParser.parseString(body).getAsJsonObject();
            JsonArray rawDocs = parsed.getAsJsonArray("documents");

            if (rawDocs == null || rawDocs.size() == 0) {
                out.print("{\"error\":{\"code\":\"400\",\"message\":\"At least one document is required\"}}");
                return;
            }

            ArrayList<DisputeDocument> documents = new ArrayList<>();
            for (var el : rawDocs) {
                JsonObject raw = el.getAsJsonObject();
                DisputeDocument doc = new DisputeDocument();
                if (raw.has("type")) doc.setType(raw.get("type").getAsString());
                doc.setBase64Content(raw.get("b64Content").getAsString());
                documents.add(doc);
            }

            DisputeSummary dispute = new DisputeSummary();
            dispute.setCaseId(id);
            Transaction response = dispute.challenge(documents).execute();

            JsonObject json = new JsonObject();
            json.addProperty("success", true);
            json.addProperty("responseCode", response.getResponseCode());
            out.print(gson.toJson(json));
        } catch (ApiException e) {
            out.print(errorJson(e));
        }
    }

    private JsonObject formatDispute(DisputeSummary d) {
        JsonObject o = new JsonObject();
        o.addProperty("caseId",                      d.getCaseId());
        o.addProperty("caseStatus",                  d.getCaseStatus());
        o.addProperty("caseStage",                   d.getCaseStage());
        if (d.getCaseAmount() != null) o.addProperty("caseAmount", d.getCaseAmount().doubleValue());
        o.addProperty("caseCurrency",                normalizeCurrency(d.getCaseCurrency()));
        o.addProperty("transactionCardType",         d.getTransactionCardType());
        o.addProperty("transactionMaskedCardNumber", d.getTransactionMaskedCardNumber());
        o.addProperty("transactionARN",              d.getTransactionARN());
        if (d.getRespondByDate() != null) o.addProperty("respondByDate", d.getRespondByDate().toInstant().toString());
        if (d.getDepositDate()   != null) o.addProperty("depositDate",   d.getDepositDate().toInstant().toString());
        if (d.getCaseIdTime()    != null) o.addProperty("caseIdTime",    d.getCaseIdTime().toInstant().toString());
        return o;
    }

    private String normalizeCurrency(String code) {
        if (code == null) return null;
        Map<String, String> map = Map.ofEntries(
            Map.entry("MX", "MXN"), Map.entry("GB", "GBP"), Map.entry("AU", "AUD"),
            Map.entry("CA", "CAD"), Map.entry("JP", "JPY"), Map.entry("EU", "EUR"),
            Map.entry("SG", "SGD"), Map.entry("HK", "HKD"), Map.entry("NZ", "NZD"),
            Map.entry("ZA", "ZAR"), Map.entry("BR", "BRL"), Map.entry("IN", "INR"),
            Map.entry("CN", "CNY"), Map.entry("SE", "SEK"), Map.entry("NO", "NOK"),
            Map.entry("DK", "DKK"), Map.entry("CH", "CHF"), Map.entry("AE", "AED")
        );
        return map.getOrDefault(code, code);
    }

    private String errorJson(Exception e) {
        JsonObject err = new JsonObject();
        JsonObject wrapper = new JsonObject();
        err.addProperty("code", "500");
        err.addProperty("message", e.getMessage() != null ? e.getMessage() : "Unknown error");
        wrapper.add("error", err);
        return gson.toJson(wrapper);
    }

    private String sandboxMockJson() {
        JsonObject json = new JsonObject();
        json.addProperty("success", true);
        json.addProperty("responseCode", "SUCCESS");
        json.addProperty("sandbox", true);
        json.addProperty("note", SANDBOX_NOTE);
        return gson.toJson(json);
    }

    private void setCors(HttpServletResponse res) {
        res.setHeader("Access-Control-Allow-Origin", "*");
        res.setHeader("Access-Control-Allow-Methods", "GET, POST, OPTIONS");
        res.setHeader("Access-Control-Allow-Headers", "Content-Type");
    }

    private int intParam(HttpServletRequest req, String name, int def) {
        try { return Integer.parseInt(req.getParameter(name)); } catch (Exception e) { return def; }
    }

    private String extractId(String path) {
        String[] parts = path.split("/");
        return parts.length >= 3 ? parts[2] : "";
    }

    private record ConfigResponse(String environment, String backend) {}
}
