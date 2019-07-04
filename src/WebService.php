<?php namespace GoogleMaps;

use Illuminate\Support\Arr;

/**
 * Description of GoogleMaps
 *
 * @author Alexander Pechkarev <alexpechkarev@gmail.com>
 */
class WebService{


    /*
    |--------------------------------------------------------------------------
    | Default Endpoint
    |--------------------------------------------------------------------------
    |
    */
    protected $endpoint;



    /*
    |--------------------------------------------------------------------------
    | Web Service
    |--------------------------------------------------------------------------
    |
    |
    |
    */
    protected $service;


    /*
    |--------------------------------------------------------------------------
    | API Key
    |--------------------------------------------------------------------------
    |
    |
    |
    */
    protected $key;


    /*
    |--------------------------------------------------------------------------
    | Service URL
    |--------------------------------------------------------------------------
    |
    |
    |
    */
    protected $requestUrl;

    /*
    |--------------------------------------------------------------------------
    | Verify SSL Peer
    |--------------------------------------------------------------------------
    |
    |
    |
    */
    protected $verifySSL;

    /**
     * Setting endpoint
     * @param string $key
     * @return $this
     */
    public function setEndpoint( $key = 'json' ){

        $this->endpoint = Arr::Get(config('googlemaps.endpoint'), $key, 'json?');

        return $this;
    }

    /**
     * Getting endpoint
     * @return string
     */
    public function getEndpoint( ){

        return $this->endpoint;
    }

    /**
     * Set parameter by key
     * @param string $key
     * @param string $value
     * @return $this
     */
    public function setParamByKey($key, $value){

         if( array_key_exists( $key, Arr::dot( $this->service['param'] ) ) ){
             Arr::set($this->service['param'], $key, $value);
         }

         return $this;
    }

    /**
     * Get parameter by the key
     * @param string $key
     * @return string|null
     */
    public function getParamByKey($key){
        return Arr::get($this->service['param'], $key, null);
    }

    /**
     * Set all parameters at once
     * @param array $param
     * @return $this
     */
    public function setParam( $param ){

        $this->service['param'] = array_merge( $this->service['param'], $param );

        return $this;
    }

    /**
     * Return parameters array
     * @return array
     */
    public function getParam(){
        return $this->service['param'];
    }

    /**
     * Get Web Service Response
     * @param string $needle - response key
     * @return string
     */
    public function get( $needle = false ){

        return empty( $needle )
                ? $this->getResponse()
                : $this->getResponseByKey( $needle );
    }

    /**
     * Get response value by key
     * @param string $needle - retrieves response parameter using "dot" notation
     * @param int $offset
     * @param int $length
     * @return array
     */
    public function getResponseByKey( $needle = false, $offset = 0, $length = null ){

        // set response to json
        $this->setEndpoint('json');

        // set default key parameter
        $needle = empty( $needle )
                    ? metaphone($this->service['responseDefaultKey'])
                    : metaphone($needle);

        // get response
        $obj = json_decode( $this->get(), true);

        // flatten array into single level array using 'dot' notation
        $obj_dot = Arr::dot($obj);
        // create empty response
        $response = [];
        // iterate
        foreach( $obj_dot as $key => $val){

            // Calculate the metaphone key and compare with needle
            if( strcmp( metaphone($key, strlen($needle)), $needle) === 0 ){
                // set response value
                Arr::set($response, $key, $val);
            }
        }

        // finally extract slice of the array
        #return array_slice($response, $offset, $length);

        return count($response) < 1
               ? $obj
               : $response;
    }

    /**
     * Get response status
     * @return mixed
     */
    public function getStatus(){

        // set response to json
        $this->setEndpoint('json');

        // get response
        $obj = json_decode( $this->get(), true);

        return Arr::get($obj, 'status', null);
    }

    /*
    |--------------------------------------------------------------------------
    | Protected methods
    |--------------------------------------------------------------------------
    |
    */

    /**
     * Setup service parameters
     * @throws \ErrorException
     */
    protected function build( $service ){

            $this->validateConfig( $service );

            // set default endpoint
            $this->setEndpoint();

            // set web service parameters
            $this->service = config('googlemaps.service.'.$service);

            // is service key set, use it, otherwise use default key
            $this->key = empty( $this->service['key'] )
                         ? config('googlemaps.key')
                         : $this->service['key'];

            // set service url
            $this->requestUrl = $this->service['url'];

            // is ssl_verify_peer key set, use it, otherwise use default key
            $this->verifySSL = empty(config('googlemaps.ssl_verify_peer'))
                            ? FALSE
                            :config('googlemaps.ssl_verify_peer');

            $this->clearParameters();
    }

    /**
     * Validate configuration file
     * @throws \ErrorException
     */
    protected function validateConfig( $service ){

            // Check for config file
            if( !\Config::has('googlemaps')){

                throw new \ErrorException('Unable to find config file.');
            }

            // Validate Key parameter
            if(!array_key_exists('key', config('googlemaps') ) ){

                throw new \ErrorException('Unable to find Key parameter in configuration file.');
            }

            // Validate Key parameter
            if(!array_key_exists('service', config('googlemaps') )
                    && !array_key_exists($service, config('googlemaps.service')) ){
                throw new \ErrorException('Web service must be declared in the configuration file.');
            }

            // Validate Endpoint
            $endpointCount = count(config('googlemaps.endpoint'));
            $endpointsKeyExists = array_key_exists('endpoint', config('googlemaps'));

            if($endpointsKeyExists === false || $endpointCount < 1){
                throw new \ErrorException('End point must not be empty.');
            }


    }

    /**
     * Get Web Service Response
     * @return string
     * @throws \ErrorException
     */
    protected function getResponse(){

        $post = false;

        // use output parameter if required by the service
        $this->requestUrl.= $this->service['endpoint']
                            ? $this->endpoint
                            : '';

        // set API Key
        $this->requestUrl.= 'key='.urlencode( $this->key );

        switch( $this->service['type'] ){
            case 'POST':
                $post = json_encode( $this->service['param'] );
                break;
            default:
                $this->requestUrl.='&'. Parameters::getQueryString( $this->service['param'] );
                break;
        }

        return $this->make( $post );
    }

    /**
     * Make cURL request to given URL
     * @param boolean $isPost
     * @return object
     * @throws \ErrorException
     */
    protected function make( $isPost = false ){

        $ch = curl_init( $this->requestUrl );

        if( $isPost ){
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch,CURLOPT_POST, 1);
            curl_setopt($ch,CURLOPT_POSTFIELDS, $isPost );
        }

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifySSL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $output = curl_exec($ch);

        if( $output === false ){
            throw new \ErrorException( curl_error($ch) );
        }

        curl_close($ch);
        return $output;
    }

    protected function clearParameters()
    {
        Parameters::resetParams();
    }
}
