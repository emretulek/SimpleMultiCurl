# SimpleMultiCurl
basic php multi curl class

```php
try {
    //first multi curl
    $mcurl = new SimpleMultiCURL(50);

    for ($i = 0; $i < 10; $i++) {

        $curl = new SimpleCURL();
        $curl->post("http://localhost", [
            'username' => 'Admin',
            'password' => 123456
        ])
             ->noneSSL()
             ->setOptions([
                 CURLOPT_CONNECTTIMEOUT => 30,
                 CURLOPT_ENCODING       => 'gzip, deflate'
             ])
             ->setHeader([
                 'content-type' => 'text/html'
             ])
             ->setCookie([
                 'token' => 'xxxxxxxxx'
             ])
             ->followLocation(false)
            //->proxy('host:port', 'username:password')
            //->proxy('username:password@host:port')
             ->addFile('input_name', '/test.jpg');

        $mcurl->add($curl, $i);
    }

    $results = $mcurl->send();

    //second multi curl
    $mcurl2 = new SimpleMultiCURL(50);

    foreach ($results as $result) {

        $curl = new SimpleCURL();

        $curl->noneSSL()
             ->setCookie($result->getCookie())
             ->post("http://localhost");

        $mcurl2->add($curl, $result->getData());
    }

    $results2 = $mcurl2->send();

    foreach ($results2 as $result2) {
        echo $result2->getResponseCode() . '<br>';
        echo $result2->getContent() . '<br>';
        echo $result2->getHeader() . '<br>';
        print_r($result2->getCookie()) . '<br>';
        print_r($result2->getHeader()) . '<br>';
        print_r($result2->getData()) . '<br>';
    }

} catch (Exception $e) {
    echo $e->getMessage();
}
```
