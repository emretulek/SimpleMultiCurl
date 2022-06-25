<?php

class SimpleMultiCURL
{

    /**
     * @var SimpleCURL[] $simpleCURLS
     */
    private array $simpleCURLS;
    private array $curlChs;
    private array $curlsData;

    private int $batchThread;
    private $mch;

    public function __construct($batchThread = 50)
    {
        $this->batchThread = $batchThread;
        $this->mch = curl_multi_init();
    }


    /**
     * @param SimpleCURL $simpleCURL
     * @param $data
     * @return $this
     * @throws Exception
     */
    public function add(SimpleCURL $simpleCURL, $data = null): self
    {
        try {
            $this->simpleCURLS[] = $simpleCURL;
            $this->curlChs[]     = $simpleCURL->getCurlResource();
            $this->curlsData[]   = $data ?: $simpleCURL->getData();
        }catch (Exception $exception){
            throw new Exception($exception->getMessage());
        }
        return $this;
    }



    /**
     * @return array
     * @throws Exception
     */
    public function send(): array
    {
        $active = null;

        do {

            if(0 < count($this->curlChs) && $active <= $this->batchThread) {

                if ($errno = curl_multi_add_handle($this->mch, array_shift($this->curlChs))) {

                    throw new Exception(curl_multi_strerror($errno));
                }
            }

            $mexec = curl_multi_exec($this->mch, $active);
            usleep(1000);

        } while ($mexec == CURLM_OK && $active > 0);

        /* Curl error handling */
        while ($result = curl_multi_info_read($this->mch)) {
            if ($errno = $result['result']) {
                throw new Exception(curl_strerror($errno));
            }
        }

        /* Curl response */
        foreach ($this->simpleCURLS as $key => $simpleCURL){

            $ch = $simpleCURL->getCurlResource();
            $info = curl_getinfo($ch);
            $response = curl_multi_getcontent($ch);

            $simpleCURL->multCurlResponsCreater(
                $response,
                substr($response, 0, $info['header_size']),
                substr($response, $info['header_size']),
                $info,
                $this->curlsData[$key]
            );

            curl_multi_remove_handle($this->mch, $ch);
            curl_close($ch);
        }

        if($errno = curl_multi_errno($this->mch)){
            throw new Exception(curl_multi_strerror($errno));
        }

        curl_multi_close($this->mch);

        return $this->simpleCURLS;
    }
    
    
    /**
     * @return void
     */
    public function flush()
    {
        $this->simpleCURLS = [];
        $this->curlChs     = [];
        $this->curlsData   = [];
    }
}

