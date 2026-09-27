<?php
class SmtpMailer {
    private $host;
    private $port;
    private $encryption;
    private $username;
    private $password;
    private $timeout = 10;

    public function __construct($host, $port, $encryption, $username, $password) {
        $this->host = $host;
        $this->port = $port;
        $this->encryption = $encryption;
        $this->username = $username;
        $this->password = $password;
    }

    public function send($to, $subject, $message, $fromEmail, $fromName = '') {
        $hostPrefix = ($this->encryption === 'ssl') ? 'ssl://' : '';
        $socket = @fsockopen($hostPrefix . $this->host, $this->port, $errno, $errstr, $this->timeout);
        
        if (!$socket) {
            throw new Exception("SMTP Connect failed: $errstr ($errno)");
        }

        $this->readResponse($socket); // read greeting

        $this->sendCommand($socket, "EHLO " . $this->host);
        
        if ($this->encryption === 'tls') {
            $this->sendCommand($socket, "STARTTLS");
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $this->sendCommand($socket, "EHLO " . $this->host);
        }

        if ($this->username && $this->password) {
            $this->sendCommand($socket, "AUTH LOGIN");
            $this->sendCommand($socket, base64_encode($this->username));
            $this->sendCommand($socket, base64_encode($this->password));
        }

        $this->sendCommand($socket, "MAIL FROM:<" . $fromEmail . ">");
        $this->sendCommand($socket, "RCPT TO:<" . $to . ">");
        $this->sendCommand($socket, "DATA");

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . ($fromName ? "=?UTF-8?B?".base64_encode($fromName)."?= <$fromEmail>" : $fromEmail) . "\r\n";
        $headers .= "To: <$to>\r\n";
        $headers .= "Subject: =?UTF-8?B?".base64_encode($subject)."?=\r\n";
        
        $data = $headers . "\r\n" . $message . "\r\n.";
        $this->sendCommand($socket, $data);
        
        $this->sendCommand($socket, "QUIT");
        fclose($socket);
        return true;
    }

    private function sendCommand($socket, $command) {
        fwrite($socket, $command . "\r\n");
        return $this->readResponse($socket);
    }

    private function readResponse($socket) {
        $data = "";
        while ($str = fgets($socket, 515)) {
            $data .= $str;
            if (substr($str, 3, 1) == " ") {
                break;
            }
        }
        return $data;
    }
}
