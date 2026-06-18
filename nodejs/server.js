/**
 * Dispute Management — Node.js / Express
 *
 * Endpoints:
 *   GET  /config
 *   GET  /disputes
 *   GET  /disputes/:id
 *   POST /disputes/:id/accept
 *   POST /disputes/:id/challenge
 */

import express from 'express';
import * as dotenv from 'dotenv';
import {
  ServicesContainer,
  GpApiConfig,
  Environment,
  Channel,
  ReportingService,
  SearchCriteria,
  DataServiceCriteria,
  DisputeSortProperty,
  SortDirection,
  DisputeSummary,
} from 'globalpayments-api';

dotenv.config();

const CURRENCY_MAP = {
  MX: 'MXN', GB: 'GBP', AU: 'AUD', CA: 'CAD', JP: 'JPY', EU: 'EUR',
  SG: 'SGD', HK: 'HKD', NZ: 'NZD', ZA: 'ZAR', BR: 'BRL', IN: 'INR',
  CN: 'CNY', SE: 'SEK', NO: 'NOK', DK: 'DKK', CH: 'CHF', AE: 'AED',
};
function normalizeCurrency(code) { return (code && CURRENCY_MAP[code]) || code || null; }

const app = express();
const port = process.env.PORT || 8000;

app.use(express.static('.'));
app.use(express.json());

app.use((req, res, next) => {
  res.header('Access-Control-Allow-Origin', '*');
  res.header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.header('Access-Control-Allow-Headers', 'Content-Type');
  if (req.method === 'OPTIONS') return res.status(200).end();
  next();
});

function configureGpApi() {
  const config = new GpApiConfig();
  config.appId = process.env.GP_APP_ID;
  config.appKey = process.env.GP_APP_KEY;
  config.channel = Channel.CardNotPresent;
  config.environment =
    (process.env.GP_ENVIRONMENT || 'sandbox').toLowerCase() === 'production'
      ? Environment.PRODUCTION
      : Environment.TEST;
  ServicesContainer.configureService(config);
}

configureGpApi();

function formatDispute(d) {
  return {
    caseId:                      d.caseId,
    caseStatus:                  d.caseStatus,
    caseStage:                   d.caseStage,
    caseAmount:                  d.caseAmount,
    caseCurrency:                normalizeCurrency(d.caseCurrency),
    transactionCardType:         d.transactionCardType,
    transactionMaskedCardNumber: d.transactionMaskedCardNumber,
    transactionARN:              d.transactionARN,
    respondByDate:               d.respondByDate?.toISOString() ?? null,
    depositDate:                 d.depositDate?.toISOString() ?? null,
    caseIdTime:                  d.caseIdTime?.toISOString() ?? null,
  };
}

// GET /config
app.get('/config', (req, res) => {
  res.json({ environment: process.env.GP_ENVIRONMENT || 'sandbox', backend: 'nodejs' });
});

// GET /disputes
app.get('/disputes', async (req, res) => {
  try {
    const page     = Math.max(1, parseInt(req.query.page) || 1);
    const pageSize = Math.min(50, Math.max(1, parseInt(req.query.pageSize) || 10));
    const { status, stage, startDate, endDate } = req.query;

    const start = startDate
      ? new Date(startDate)
      : new Date(Date.now() - 90 * 24 * 60 * 60 * 1000);

    let query = ReportingService.findDisputesPaged(page, pageSize)
      .orderBy(DisputeSortProperty.Id, SortDirection.Descending)
      .where(DataServiceCriteria.StartStageDate, start);

    if (endDate) {
      const end = new Date(endDate);
      end.setHours(23, 59, 59, 999);
      query = query.andWith(DataServiceCriteria.EndStageDate, end);
    }
    if (status)  query = query.andWith(SearchCriteria.DisputeStatus, status);
    if (stage)   query = query.andWith(SearchCriteria.DisputeStage, stage);

    const result = await query.execute();

    res.json({
      disputes:    (result.result || []).map(formatDispute),
      totalCount:  result.totalRecordCount ?? result.result?.length ?? 0,
      totalPages:  result.totalRecordCount ? Math.ceil(result.totalRecordCount / pageSize) : 1,
      currentPage: page,
    });
  } catch (err) {
    console.error('list-disputes error:', err.message);
    res.status(500).json({ error: { code: err.responseCode || '500', message: err.message } });
  }
});

// GET /disputes/:id
app.get('/disputes/:id', async (req, res) => {
  try {
    const d = await ReportingService.disputeDetail(req.params.id).execute();
    res.json({ dispute: { ...formatDispute(d), merchantHierarchy: d.merchantHierarchy } });
  } catch (err) {
    console.error('dispute-detail error:', err.message);
    res.status(500).json({ error: { code: err.responseCode || '500', message: err.message } });
  }
});

const SANDBOX_NOTE = 'Write operations are simulated in sandbox and not submitted to the card network. This action would execute against a live dispute in production.';
const isSandbox = () => (process.env.GP_ENVIRONMENT || 'sandbox').toLowerCase() !== 'production';

// POST /disputes/:id/accept
app.post('/disputes/:id/accept', async (req, res) => {
  try {
    if (isSandbox()) return res.json({ success: true, responseCode: 'SUCCESS', sandbox: true, note: SANDBOX_NOTE });
    const dispute = new DisputeSummary();
    dispute.caseId = req.params.id;
    const response = await dispute.accept().execute();
    res.json({ success: true, responseCode: response.responseCode });
  } catch (err) {
    console.error('accept-dispute error:', err.message);
    res.status(500).json({ error: { code: err.responseCode || '500', message: err.message } });
  }
});

// POST /disputes/:id/challenge
app.post('/disputes/:id/challenge', async (req, res) => {
  try {
    if (isSandbox()) return res.json({ success: true, responseCode: 'SUCCESS', sandbox: true, note: SANDBOX_NOTE });
    const rawDocs = req.body.documents || [];
    if (!rawDocs.length) {
      return res.status(400).json({ error: { code: '400', message: 'At least one document is required' } });
    }

    const documents = rawDocs.map((raw) => ({
      type:        raw.type || null,
      b64_content: raw.b64Content || '',
    }));

    const dispute = new DisputeSummary();
    dispute.caseId = req.params.id;

    const response = await dispute.challenge(documents).execute();
    res.json({ success: true, responseCode: response.responseCode });
  } catch (err) {
    console.error('challenge-dispute error:', err.message);
    res.status(500).json({ error: { code: err.responseCode || '500', message: err.message } });
  }
});

app.listen(port, () => console.log(`Dispute Management (Node.js) running on http://localhost:${port}`));
