/**
 * Gateway PIX para Vercel (substitui pagamento/api/gateway.php).
 * Configure GATEWAY_API no painel da Vercel (Settings → Environment Variables).
 */

const NOMES = ['Ana', 'Maria', 'Juliana', 'Fernanda', 'Carlos', 'Joao', 'Pedro', 'Lucas', 'Rafael', 'Marcos'];
const SOBRENOMES = ['Silva', 'Santos', 'Oliveira', 'Souza', 'Pereira', 'Costa', 'Almeida', 'Rodrigues'];
const OFERTA_NOME = process.env.OFERTA_NOME || 'Deposito';

function pick(obj, keys, fallback = '') {
  for (const key of keys) {
    const v = obj[key];
    if (v !== undefined && v !== null && String(v).trim() !== '') {
      return v;
    }
  }
  return fallback;
}

function gerarCpfValido() {
  let cpf = '';
  for (let i = 0; i < 9; i++) cpf += Math.floor(Math.random() * 10);
  for (let t = 9; t < 11; t++) {
    let d = 0;
    for (let c = 0; c < t; c++) d += Number(cpf[c]) * (t + 1 - c);
    d = (10 * d) % 11 % 10;
    cpf += d;
  }
  return cpf;
}

function clientePadrao() {
  const nome =
    NOMES[Math.floor(Math.random() * NOMES.length)] +
    ' ' +
    SOBRENOMES[Math.floor(Math.random() * SOBRENOMES.length)] +
    ' ' +
    SOBRENOMES[Math.floor(Math.random() * SOBRENOMES.length)];
  const emailNome = nome.toLowerCase().replace(/[^a-z0-9]/gi, '');
  return {
    nome,
    email: `${emailNome}${Math.floor(Math.random() * 9900) + 100}@gmail.com`,
    telefone: `119${Math.floor(Math.random() * 90000000) + 10000000}`,
    cpf: gerarCpfValido(),
  };
}

function normalizarStatus(status) {
  const s = String(status || '').toLowerCase();
  if (['approved', 'completed', 'paid', 'confirmed'].includes(s)) return 'COMPLETED';
  if (['cancelled', 'canceled', 'failed', 'refused', 'expired'].includes(s)) return 'CANCELLED';
  return 'PENDING';
}

function gatewayApiUrl() {
  return (
    process.env.GATEWAY_API ||
    process.env.DUTTYFY_PIX_URL_ENCRYPTED ||
    ''
  ).trim();
}

function responder(res, payload, httpCode = 200) {
  res.statusCode = httpCode;
  res.setHeader('Content-Type', 'application/json; charset=utf-8');
  res.end(JSON.stringify(payload));
}

async function parseEntrada(req) {
  const query = req.query || {};
  let body = req.body;

  if (typeof body === 'string' && body.trim() !== '') {
    try {
      body = JSON.parse(body);
    } catch {
      body = Object.fromEntries(new URLSearchParams(body));
    }
  }

  if (!body || typeof body !== 'object') {
    body = {};
  }

  return { ...query, ...body };
}

async function chamarGateway(url, method, postfields) {
  const opts = {
    method,
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
  };

  if (method === 'POST' && postfields) {
    opts.body = JSON.stringify(postfields);
  }

  const response = await fetch(url, opts);
  const raw = await response.text();
  let data = {};
  try {
    data = raw ? JSON.parse(raw) : {};
  } catch {
    data = {};
  }

  return { response, data, raw };
}

module.exports = async function handler(req, res) {
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Accept');

  if (req.method === 'OPTIONS') {
    res.statusCode = 200;
    res.end();
    return;
  }

  if (req.method !== 'GET' && req.method !== 'POST') {
    responder(res, { success: false, error: 'Metodo nao permitido' }, 405);
    return;
  }

  const gatewayApi = gatewayApiUrl();
  if (!gatewayApi) {
    responder(
      res,
      {
        success: false,
        erro: 1,
        erroMsg: 'GATEWAY_API nao configurada nas variaveis de ambiente da Vercel',
        error: 'GATEWAY_API nao configurada nas variaveis de ambiente da Vercel',
      },
      500
    );
    return;
  }

  const entrada = await parseEntrada(req);
  let acao = String(pick(entrada, ['acao'], '')).trim();

  if (!acao) {
    if (req.method === 'POST') {
      acao = 'criar';
    } else if (pick(entrada, ['payment_id', 'transactionId', 'id'], '') !== '') {
      acao = 'verificar';
    }
  }

  if (acao === 'criar') {
    const cliente = clientePadrao();
    let valor = parseFloat(String(pick(entrada, ['valor', 'amount', 'price', 'valor-doacao'], 20)).replace(',', '.'));
    if (Number.isNaN(valor) || valor < 5) {
      responder(
        res,
        {
          success: false,
          erro: 1,
          erroMsg: 'Valor minimo para PIX e R$ 5,00',
          error: 'Valor minimo para PIX e R$ 5,00',
        },
        400
      );
      return;
    }

    const valorCentavos = Math.round(valor * 100);
    let nome = String(pick(entrada, ['nome', 'nome_doador'], cliente.nome)).trim();
    if (!nome || nome.toLowerCase().startsWith('anon')) {
      nome = cliente.nome;
    }

    const email = String(pick(entrada, ['email'], cliente.email)).trim() || cliente.email;
    const telefone =
      String(pick(entrada, ['telefone', 'phone'], cliente.telefone)).replace(/\D/g, '') || cliente.telefone;
    const cpf =
      String(pick(entrada, ['cpf', 'document'], cliente.cpf)).replace(/\D/g, '') || cliente.cpf;
    const utm = decodeURIComponent(String(pick(entrada, ['utm', 'utm_raw'], '')));

    const postfields = {
      utm,
      item: {
        price: valorCentavos,
        title: OFERTA_NOME,
        quantity: 1,
      },
      amount: valorCentavos,
      customer: {
        name: nome,
        email,
        phone: telefone,
        document: cpf,
      },
      description: 'Pagamento via Pix',
      paymentMethod: 'PIX',
    };

    try {
      const { response, data, raw } = await chamarGateway(gatewayApi, 'POST', postfields);

      if (!response.ok || data.message || data.error) {
        const msg = data.message || data.error || `API retornou HTTP ${response.status}`;
        responder(
          res,
          { success: false, erro: 1, erroMsg: msg, error: msg, detalhes: data },
          response.status >= 400 ? response.status : 500
        );
        return;
      }

      const transactionId = data.transactionId || '';
      const pixCode = data.pixCode || '';

      if (!transactionId || !pixCode) {
        responder(
          res,
          {
            success: false,
            erro: 1,
            erroMsg: 'API nao retornou transactionId ou pixCode',
            error: 'API nao retornou transactionId ou pixCode',
            detalhes: data,
          },
          500
        );
        return;
      }

      responder(res, {
        ok: true,
        success: true,
        payment_id: transactionId,
        paymentId: transactionId,
        transactionId,
        pixCode,
        qrCode: pixCode,
        status: data.status || 'PENDING',
      });
    } catch (err) {
      responder(
        res,
        {
          success: false,
          erro: 1,
          erroMsg: err.message || 'Erro ao conectar com gateway',
          error: err.message || 'Erro ao conectar com gateway',
        },
        500
      );
    }
    return;
  }

  if (acao === 'verificar') {
    const paymentId = String(pick(entrada, ['payment_id', 'transactionId', 'id'], '')).trim();
    if (!paymentId) {
      responder(
        res,
        {
          success: false,
          erro: 1,
          erroMsg: 'Parametro obrigatorio faltando: payment_id',
          error: 'Parametro obrigatorio faltando: payment_id',
        },
        400
      );
      return;
    }

    const url = `${gatewayApi.replace(/\?$/, '')}?transactionId=${encodeURIComponent(paymentId)}`;

    try {
      const { response, data } = await chamarGateway(url, 'GET');
      if (!response.ok) {
        responder(
          res,
          {
            success: false,
            error: data.message || data.error || `HTTP ${response.status}`,
          },
          response.status
        );
        return;
      }

      const status = normalizarStatus(data.status);
      responder(res, {
        success: true,
        paymentId,
        transactionId: paymentId,
        status,
        isPaid: status === 'COMPLETED',
      });
    } catch (err) {
      responder(res, { success: false, error: err.message || 'Erro ao consultar pagamento' }, 500);
    }
    return;
  }

  responder(res, { success: false, erro: 1, erroMsg: 'Acao nao encontrada', error: 'Acao nao encontrada' }, 400);
};
