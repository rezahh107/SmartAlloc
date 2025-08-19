const http = require('http');
const url = process.env.WP_BASE_URL || 'http://localhost:8080';
const deadline = Date.now() + 120000;
(function ping(){
  http.get(url, res => res.statusCode === 200 ? process.exit(0)
    : Date.now()<deadline ? setTimeout(ping, 1500) : process.exit(1))
    .on('error', ()=> Date.now()<deadline ? setTimeout(ping,1500) : process.exit(1));
})();
