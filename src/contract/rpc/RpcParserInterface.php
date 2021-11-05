<?php

namespace BusyPHP\swoole\contract\rpc;

use BusyPHP\swoole\rpc\Protocol;

interface RpcParserInterface
{
    const EOF = "\r\n\r\n";
    
    
    /**
     * @param Protocol $protocol
     *
     * @return string
     */
    public function encode(Protocol $protocol) : string;
    
    
    /**
     * @param string $string
     *
     * @return Protocol
     */
    public function decode(string $string) : Protocol;
    
    
    /**
     * @param string $string
     *
     * @return mixed
     */
    public function decodeResponse(string $string);
    
    
    /**
     * @param mixed $result
     *
     * @return string
     */
    public function encodeResponse($result) : string;
}
