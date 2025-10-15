<?php
/**
 * Genera un UUID v4 válido según RFC 4122
 * @return string UUID v4 en formato 8-4-4-4-12
 */
function generateUUID(): string {
    $data = random_bytes(16);
    
    // Set version (4) and variant (RFC 4122)
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // version 4
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variant RFC 4122
    
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
