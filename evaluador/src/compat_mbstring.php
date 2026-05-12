<?php
 
declare(strict_types=1);
 
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $string, ?string $encoding = null): int
    {
        return preg_match_all('/./us', $string, $matches);
    }
}
 
if (!function_exists('mb_substr')) {
    function mb_substr(string $string, int $start, ?int $length = null, ?string $encoding = null): string
    {
        $chars = preg_split('//u', $string, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) {
            return $length === null ? substr($string, $start) : substr($string, $start, $length);
        }
 
        $count = count($chars);
        if ($start < 0) {
            $start = max(0, $count + $start);
        }
 
        if ($length === null) {
            return implode('', array_slice($chars, $start));
        }
 
        if ($length < 0) {
            $sliceLength = max(0, ($count - $start) + $length);
            return implode('', array_slice($chars, $start, $sliceLength));
        }
 
        return implode('', array_slice($chars, $start, $length));
    }
}
 
if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $string, ?string $encoding = null): string
    {
        $map = [
            'Á'=>'á','À'=>'à','Ä'=>'ä','Â'=>'â','Ã'=>'ã','Å'=>'å','Æ'=>'æ','Ç'=>'ç','É'=>'é','È'=>'è','Ë'=>'ë','Ê'=>'ê',
            'Í'=>'í','Ì'=>'ì','Ï'=>'ï','Î'=>'î','Ñ'=>'ñ','Ó'=>'ó','Ò'=>'ò','Ö'=>'ö','Ô'=>'ô','Õ'=>'õ','Ø'=>'ø',
            'Ú'=>'ú','Ù'=>'ù','Ü'=>'ü','Û'=>'û','Ý'=>'ý','Ÿ'=>'ÿ','Š'=>'š','Ž'=>'ž'
        ];
 
        return strtr(strtolower($string), $map);
    }
}
 
if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper(string $string, ?string $encoding = null): string
    {
        $map = [
            'á'=>'Á','à'=>'À','ä'=>'Ä','â'=>'Â','ã'=>'Ã','å'=>'Å','æ'=>'Æ','ç'=>'Ç','é'=>'É','è'=>'È','ë'=>'Ë','ê'=>'Ê',
            'í'=>'Í','ì'=>'Ì','ï'=>'Ï','î'=>'Î','ñ'=>'Ñ','ó'=>'Ó','ò'=>'Ò','ö'=>'Ö','ô'=>'Ô','õ'=>'Õ','ø'=>'Ø',
            'ú'=>'Ú','ù'=>'Ù','ü'=>'Ü','û'=>'Û','ý'=>'Ý','ÿ'=>'Ÿ','š'=>'Š','ž'=>'Ž'
        ];
 
        return strtr(strtoupper($string), $map);
    }
}
 
if (!function_exists('mb_stripos')) {
    function mb_stripos(string $haystack, string $needle, int $offset = 0, ?string $encoding = null): int|false
    {
        $haystackLower = mb_strtolower($haystack, $encoding ?? 'UTF-8');
        $needleLower = mb_strtolower($needle, $encoding ?? 'UTF-8');
        return strpos($haystackLower, $needleLower, $offset);
    }
}