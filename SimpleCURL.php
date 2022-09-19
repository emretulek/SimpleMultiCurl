<?php

class SimpleCURL
{
    private $ch;
    private array $headers = [];
    private array $options = [];
    private string $url;
    private string $method = 'GET';
    private $postData;
    private array $files = [];
    private string $response = '';
    private string $responseContent = '';
    private string $responseHeaders = '';
    private array $info = [];
    private string $error = '';
    private array $avilableMethods = ['GET', 'POST', 'PUT', 'PATCH', 'OPTIONS', 'HEAD', 'DELETE'];
    private $data;
    private bool $isPrepared = false;
    private bool $multipart = false;

    /**
     * Sınıf kurucusu
     *
     * @param $data
     * Özellikle multicurl ile istek dizinlerini takip için kullanılabilir
     */
    public function __construct($data = null)
    {
        $this->ch   = curl_init();
        $this->data = $data;

        $this->setOptions([
            CURLOPT_HEADER         => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);
    }


    /**
     * @param $name
     * CURLOPT_XXXXX ön tanımlı sabitler
     *
     * @param $value
     * CURLOPT_XXXXX ön tanımlı sabitlerin değerleri
     *
     * @return $this
     */
    public function setOption($name, $value): self
    {
        $this->options[$name] = $value;

        return $this;
    }


    /**
     * @param array $options
     * [CURLOPT_XXXXX => value] ön tanımlı sabitler ve değerlerinden oluşan dizi
     *
     * @return $this
     */
    public function setOptions(array $options): self
    {
        $this->options = $options + $this->options;

        return $this;
    }


    /**
     * @param array $headers
     * ["Key" => "value"] http header değerleri
     *
     * @return $this
     */
    public function setHeader(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);

        return $this;
    }


    /**
     * @param array $headers
     * $header[] = "Key: value" intiger indexlere sahip http header string değerleri
     *
     * @return $this
     */
    public function setHeaderCurlType(array $headers): self
    {
        foreach ($headers as $header) {
            $headerParts = explode(":", $header);

            if (isset($headerParts[0]) && isset($headerParts[1])) {
                $this->headers[trim($headerParts[0])] = trim($headerParts[1]);
            }
        }

        return $this;
    }


    /**
     * Curl isteklerini çağırılmaya hazır hale getiren method
     * @throws Exception
     */
    private function prepareRequest()
    {
        if (!in_array($this->method, $this->avilableMethods)) {
            throw new Exception("Avilable methods are [" . implode(',', $this->avilableMethods) . "]");
        }

        if (!filter_var($this->url, FILTER_VALIDATE_URL)) {
            throw new Exception("Invalid url");
        }

        $this->setOptions([
            CURLOPT_URL           => $this->url,
            CURLOPT_CUSTOMREQUEST => $this->method,
        ]);

        if ($this->postData) {

            if ($this->files) {
                $this->multipart = true;
                $this->postData += $this->files;
            }

            if($this->multipart === false && (is_array($this->postData) || is_object($this->postData))){
                $this->postData = http_build_query($this->postData);
            }

            $this->setOptions([
                CURLOPT_POSTFIELDS => $this->postData
            ]);
        }

        $headers = [];

        foreach ($this->headers as $key => $val) {
            $headers[] = $key . ': ' . $val;
        }

        $this->setOption(CURLOPT_HTTPHEADER, $headers);

        curl_setopt_array($this->ch, $this->options);

        $this->isPrepared = true;
    }


    /**
     * Hazırlanan curl isteğinin gönderilmesi
     *
     * @return bool|string
     * @throws Exception
     */
    public function send()
    {
        $this->prepareRequest();

        $this->response = curl_exec($this->ch);
        $this->info     = curl_getinfo($this->ch);

        $this->responseHeaders = substr($this->response, 0, $this->info['header_size']);
        $this->responseContent = substr($this->response, $this->info['header_size']);

        if ($error = curl_error($this->ch)) {
            $this->error = $error;
            throw new Exception($this->error);
        }

        curl_close($this->ch);

        return $this->response;
    }


    /**
     * @param $url
     * İstek yapılacak adres
     *
     * @param array $parameters
     * Get methoduna eklenecek parametreler [key => value]
     *
     * @return $this
     */
    public function get($url, array $parameters = []): self
    {
        if ($baseUrl = strstr($url, '?', true)) {

            $parsedUrl = parse_url($url, PHP_URL_QUERY);

            if ($parsedUrl) {
                parse_str($parsedUrl, $queryParams);
                $parameters = array_merge($parameters, $queryParams);
            }

        } else {
            $baseUrl = $url;
        }

        if ($parameters) {
            $queryString = http_build_query($parameters);
            $url         = $baseUrl . '?' . $queryString;
        }

        $this->request($url, 'GET');

        return $this;
    }


    /**
     * @param $url
     * İstek yapılacak adres
     *
     * @param array $parameters
     * Post edilecek parametreler [key => value]
     *
     * @param bool $multipart
     * Form multipart olarak gönderilecek ise true default false
     *
     * @return $this
     * @throws Exception
     */
    public function post($url, array $parameters = [], bool $multipart = false): self
    {
        if ($this->multipart = $multipart) {
            $this->setHeader([
                'Content-Type' => 'multipart/form-data'
            ]);
        }

        $this->request($url, 'POST', $parameters);

        return $this;
    }

    /**
     * @param string $url
     * İstek yapılacak adres
     *
     * @param string $method [GET, POST, DELETE, PUT, PATCH, OPTIONS, HEADER]
     *
     * @param $data
     * Post edilecek data json, querystring veya array olabilir
     * Bu method ile gönderilen data işlenmeden aktarılacaktır
     *
     * @return $this
     */
    public function request(string $url, string $method, $data = null): self
    {
        $this->method = strtoupper($method);
        $this->url = $url;
        $this->postData = $data;

        return $this;
    }


    /**
     * @param $url
     * İstek yapılacak adres
     *
     * @param $data
     * Post edilecek data json, querystring veya array olabilir
     * Bu method ile gönderilen data işlenmeden aktarılacaktır
     *
     * @return $this
     * @throws Exception
     */
    public function put($url, $data): self
    {
        $this->request($url, 'PUT', $data);

        return $this;
    }


    /**
     * @param $url
     * İstek yapılacak adres
     *
     * @param $data
     * Post edilecek data json, querystring veya array olabilir
     * Bu method ile gönderilen data işlenmeden aktarılacaktır
     *
     * @return $this
     * @throws Exception
     */
    public function patch($url, $data): self
    {
        $this->request($url, 'PATCH', $data);

        return $this;
    }


    /**
     * @param $url
     * İstek yapılacak adres
     *
     * @return $this
     * @throws Exception
     */
    public function delete($url): self
    {
        $this->request($url, 'DELETE');

        return $this;
    }


    /**
     * @param $url
     * İstek yapılacak adres
     *
     * @return $this
     * @throws Exception
     */
    public function options($url): self
    {
        $this->request($url, 'OPTIONS');

        return $this;
    }


    /**
     * @param $url
     * İstek yapılacak adres
     *
     * @return $this
     * @throws Exception
     */
    public function head($url): self
    {
        $this->request($url, 'HEAD');

        return $this;
    }


    /**
     * @param $input_name
     * Upload edilecek input name attribute
     *
     * @param $file_path
     * Upload edilecek dosya yolu
     *
     * @param string $mime_type
     * Upload edilecek dosyanın mime tipi eklenmezse otomatik tespit edilmeye çalışır
     *
     * @param string $name
     * Upload edilecek dosyanın adı eklenmezse dosya ismi ile gönderilir
     *
     * @return $this
     * @throws Exception
     */
    public function addFile($input_name, $file_path, string $mime_type = "", string $name = ""): self
    {
        if (!$file_path = realpath($file_path)) {
            throw new Exception("wrong file or directory path: " . $file_path);
        }

        if ($name) {
            $name = basename($file_path);
        }

        if ($mime_type) {
            $mime_type = mime_content_type($file_path);
        }

        $this->files[$input_name] = curl_file_create($file_path, $mime_type, $name);

        return $this;
    }


    /**
     * CURLOPT_SSL_VERIFYHOST, CURLOPT_SSL_VERIFYPEER seçenekleri false ayarlanır
     *
     * @return $this
     */
    public function noneSSL(): self
    {
        $this->setOptions([
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        return $this;
    }


    /**
     * Proxy kullanımı
     *
     * @param string $proxy
     * host:port
     *
     * @param string|null $userpwd
     * username:password
     *
     * @return $this
     */
    public function proxy(string $proxy, ?string $userpwd = null): self
    {
        $this->setOption(CURLOPT_PROXY, $proxy);

        if ($userpwd) {
            $this->setOption(CURLOPT_PROXYUSERPWD, $userpwd);
        }

        return $this;
    }


    /**
     * CURLOPT_FOLLOWLOCATION değerini yeniden belirler
     *
     * @param bool $value
     *
     * @return $this
     */
    public function followLocation(bool $value): self
    {
        $this->setOption(CURLOPT_FOLLOWLOCATION, $value);

        return $this;
    }


    /**
     * Varsa hata mesajı
     *
     * @return string
     */
    public function getError(): string
    {
        return $this->error;
    }


    /**
     * Header bilgisini de içeren yanıt
     *
     * @return string
     */
    public function getResponse(): string
    {
        return $this->response;
    }


    /**
     * Header bilgisi dahil edilmeden text yanıt
     *
     * @return string
     */
    public function getContent(): string
    {
        return $this->responseContent;
    }


    /**
     * $name parametresi ayarlanmışsa string değer döner, parametre hatalı ise boş string döner,
     * $name parametresi ayarlanmamışsa array olarak header bilgisi döner.
     *
     * @param $name
     * @return array|int|mixed|string
     */
    public function getHeader($name = null)
    {
        $headers    = [];
        $rawHeaders = explode(PHP_EOL, $this->responseHeaders);

        foreach ($rawHeaders as $rawHeader) {

            $headerParts = explode(':', $rawHeader, 2);

            if (isset($headerParts[0], $headerParts[1])) {

                $headerParts[0] = strtolower($headerParts[0]);

                if (isset($headers[$headerParts[0]])) {

                    if (is_string($headers[$headerParts[0]])) {
                        $stringVal                  = $headers[$headerParts[0]];
                        $headers[$headerParts[0]]   = [];
                        $headers[$headerParts[0]][] = $stringVal;
                    }

                    $headers[$headerParts[0]][] = trim($headerParts[1]);

                } else {
                    $headers[$headerParts[0]] = trim($headerParts[1]);
                }
            }
        }

        if ($name) {
            return $headers[strtolower($name)] ?? '';
        }

        return $headers;
    }


    /**
     * $name parametresi ayarlanmışsa string cookie değeri, parametre hatalıysa boş string döner
     * $name parametresi ayarlanmamışsa array cookie listesi döner
     *
     * @param $name
     * @return array|mixed|string
     */
    public function getCookie($name = null)
    {
        $cookies = [];
        $headers = $this->getHeader();

        if (isset($headers['set-cookie'])) {

            if (!is_array($headers['set-cookie'])) {
                $headers['set-cookie'] = [$headers['set-cookie']];
            }

            foreach ($headers['set-cookie'] as $cookie) {

                if ($cookieParts = explode(';', $cookie)) {

                    if ($cookieParts2 = explode('=', $cookieParts[0], 2)) {

                        $cookies[trim($cookieParts2[0])] = trim($cookieParts2[1] ?? '');
                    }
                }
            }
        }

        if ($name) {
            return $cookies[$name] ?? '';
        }

        return $cookies;
    }


    /**
     * Sınıf kurucusuna eklenen data parametresi döner
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }


    /**
     * @param int|null $name
     * Öntanımlı curl sabitlerinden biri CURLOPT_XXXX,
     * değer girilmezse tüm seçenekler array olarak döner
     *
     * @return array|mixed|string
     */
    public function getOptions(int $name = null)
    {
        if ($name) {
            return $this->options[$name] ?? '';
        }

        return $this->options;
    }


    /**
     * İstek yapılan url
     *
     * @return array|mixed|string
     */
    public function getUrl()
    {
        return $this->getOptions(CURLOPT_URL);
    }


    /**
     * Curl isteğinin methodunu döndürür
     *
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }


    /**
     * $name parametresi ayarlanmışsa değeri,
     * $name parametresi ayarlanmamışsa array curl_getinfo değeri döner
     *
     * @param string $name
     * @return array|string
     */
    public function info(string $name = '')
    {
        if (isset($this->info[$name])) {
            return $this->info[$name];
        }

        return $this->info;
    }


    /**
     * İstekten dönen http yanıt kodu
     *
     * @return int
     */
    public function getResponseCode(): int
    {
        return $this->info['http_code'];
    }


    /**
     * Dizi olarak aktarılan cookieler header bilgisine eklenir
     *
     * @param array $cookies
     * [key => value] olarak atacank cookie bilgileri
     *
     * @return $this
     */
    public function setCookie(array $cookies): self
    {
        $cookie = '';

        if(isset($this->headers['Cookie'])){
            $cookie .= $this->headers['Cookie'] . '; ';
        }

        foreach ($cookies as $key => $val) {
            $cookie .= $key . '=' . $val . '; ';
        }

        $this->setHeader([
            'Cookie' => substr($cookie, 0, strlen($cookie) - 2)
        ]);


        return $this;
    }


    /**
     * MultiCurl yanıtlarının sınıfa aktarılması için oluşturulmuştur
     *
     * @param string $response
     * @param string $header
     * @param string $content
     * @param array $info
     * @param $data
     * @param string $error
     * @return void
     */
    public function multCurlResponsCreater(string $response, string $header, string $content, array $info, $data, string $error)
    {
        $this->response        = $response;
        $this->responseHeaders = $header;
        $this->responseContent = $content;
        $this->info            = $info;
        $this->data            = $data;
        $this->error           = $error;
    }


    /**
     * curl_init() Resource değeri
     *
     * @return resource
     * @throws Exception
     */
    public function getCurlResource()
    {
        if(!$this->isPrepared) {
            $this->prepareRequest();
        }

        return $this->ch;
    }


    /**
     * Sınıf clonlandığında yeni bir curl resource oluşturulur
     * Oluşan kopya sınıfın özelliklerini taşımasına rağmen aynı resourse değerine sahip değildir
     *
     * @return void
     */
    public function __clone()
    {
        $this->ch = curl_copy_handle($this->ch);
    }

    /**
     * Sınıf debug edildiğinde ekrana curl ayarları çıktılanır
     *
     * @return array
     */
    public function __debugInfo()
    {
        return $this->options;
    }


    /**
     * @return string
     */
    public function __toString()
    {
        return $this->responseContent;
    }
}

