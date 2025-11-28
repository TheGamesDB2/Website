<?php

class RateLimiter
{
    private $useApcu;
    private $fallbackFile;

    public function __construct()
    {
        $this->useApcu = function_exists('apcu_fetch');
        if (!$this->useApcu) {
            $this->fallbackFile = sys_get_temp_dir() . '/blocked_ips.json';
        }
    }

    public function isBlocked($ip)
    {
        if ($this->useApcu) {
            return apcu_exists('blocked_ip_' . $ip);
        } else {
            $blockedIps = $this->getFileCache();
            if (isset($blockedIps[$ip])) {
                if (time() - $blockedIps[$ip] < 86400) { // 24 hours
                    return true;
                } else {
                    // Cleanup expired
                    unset($blockedIps[$ip]);
                    $this->saveFileCache($blockedIps);
                }
            }
            return false;
        }
    }

    public function block($ip)
    {
        if ($this->useApcu) {
            apcu_store('blocked_ip_' . $ip, time(), 86400); // TTL 24 hours
        } else {
            $blockedIps = $this->getFileCache();
            $blockedIps[$ip] = time();
            $this->saveFileCache($blockedIps);
        }
    }

    private function getFileCache()
    {
        if (file_exists($this->fallbackFile)) {
            $content = file_get_contents($this->fallbackFile);
            $data = json_decode($content, true);
            return is_array($data) ? $data : [];
        }
        return [];
    }

    private function saveFileCache($data)
    {
        file_put_contents($this->fallbackFile, json_encode($data));
    }
}
