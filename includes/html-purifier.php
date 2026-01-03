<?php
/**
 * HTML Purifier Helper
 * Sichere HTML-Bereinigung für User-Content
 * 
 * @package dvdprofiler.liste
 */

/**
 * Bereinigt HTML-Content sicher
 * Entfernt gefährliche Tags, Attribute und JavaScript
 * 
 * @param string $html Roher HTML-Content
 * @param bool $allowLinks Links erlauben?
 * @return string Bereinigter HTML-Content
 */
function purifyHTML($html, $allowLinks = true) {
    if (empty($html)) {
        return '';
    }
    
    // Erlaubte Tags
    $allowedTags = [
        'p', 'br', 'b', 'i', 'u', 'strong', 'em', 
        'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 
        'blockquote', 'span', 'div'
    ];
    
    if ($allowLinks) {
        $allowedTags[] = 'a';
    }
    
    // Erlaubte Attribute pro Tag
    $allowedAttributes = [
        'a' => ['href', 'title', 'target', 'rel'],
        'span' => ['class'],
        'div' => ['class'],
        'blockquote' => ['class']
    ];
    
    // Gefährliche Protokolle in URLs
    $dangerousProtocols = ['javascript:', 'data:', 'vbscript:', 'file:'];
    
    // 1. Basis-Bereinigung mit strip_tags
    $tagsString = '<' . implode('><', $allowedTags) . '>';
    $html = strip_tags($html, $tagsString);
    
    // 2. Lade HTML in DOMDocument
    $dom = new DOMDocument();
    libxml_use_internal_errors(true); // Fehler unterdrücken
    
    // UTF-8 korrekt laden
    $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    
    // 3. Durchlaufe alle Elemente und bereinige Attribute
    $xpath = new DOMXPath($dom);
    $elements = $xpath->query('//*');
    
    foreach ($elements as $element) {
        $tagName = strtolower($element->tagName);
        
        // Entferne nicht erlaubte Tags
        if (!in_array($tagName, $allowedTags)) {
            $element->parentNode->removeChild($element);
            continue;
        }
        
        // Bereinige Attribute
        $attributes = [];
        foreach ($element->attributes as $attr) {
            $attributes[] = $attr->name;
        }
        
        foreach ($attributes as $attrName) {
            $attrName = strtolower($attrName);
            
            // Event-Handler blockieren
            if (strpos($attrName, 'on') === 0) {
                $element->removeAttribute($attrName);
                continue;
            }
            
            // Nur erlaubte Attribute behalten
            if (!isset($allowedAttributes[$tagName]) || 
                !in_array($attrName, $allowedAttributes[$tagName])) {
                $element->removeAttribute($attrName);
                continue;
            }
            
            // Spezielle Checks für bestimmte Attribute
            if ($attrName === 'href') {
                $href = $element->getAttribute('href');
                
                // Gefährliche Protokolle blockieren
                foreach ($dangerousProtocols as $protocol) {
                    if (stripos($href, $protocol) === 0) {
                        $element->removeAttribute('href');
                        break;
                    }
                }
                
                // target="_blank" automatisch rel="noopener noreferrer" hinzufügen
                if ($element->getAttribute('target') === '_blank') {
                    $element->setAttribute('rel', 'noopener noreferrer');
                }
            }
            
            if ($attrName === 'src') {
                $src = $element->getAttribute('src');
                
                // Gefährliche Protokolle blockieren
                foreach ($dangerousProtocols as $protocol) {
                    if (stripos($src, $protocol) === 0) {
                        $element->removeAttribute('src');
                        break;
                    }
                }
            }
        }
    }
    
    // 4. Zurück zu HTML
    $output = '';
    foreach ($dom->documentElement->childNodes as $node) {
        $output .= $dom->saveHTML($node);
    }
    
    // 5. Final cleanup
    $output = trim($output);
    
    return $output;
}

/**
 * Bereinigt HTML und konvertiert Zeilenumbrüche
 * Nützlich wenn Content aus Textarea kommt
 * 
 * @param string $text Text mit Zeilenumbrüchen
 * @param bool $allowLinks Links erlauben?
 * @return string Bereinigter HTML-Content
 */
function purifyHTMLWithBreaks($text, $allowLinks = true) {
    // Zeilenumbrüche zu <br> konvertieren
    $html = nl2br($text);
    
    return purifyHTML($html, $allowLinks);
}

/**
 * Schnelle Bereinigung nur mit strip_tags
 * Für wenn DOMDocument nicht verfügbar ist
 * 
 * @param string $html Roher HTML-Content
 * @return string Bereinigter HTML-Content
 */
function purifyHTMLSimple($html) {
    if (empty($html)) {
        return '';
    }
    
    // Erlaubte Tags
    $allowedTags = '<p><br><b><i><u><strong><em><ul><ol><li><h1><h2><h3><h4><blockquote><span><div><a>';
    
    // Basis-Bereinigung
    $html = strip_tags($html, $allowedTags);
    
    // Gefährliche Patterns entfernen
    $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i', '', $html);
    $html = preg_replace('/on\w+\s*=\s*["\']?[^"\']*["\']?/i', '', $html); // Event-Handler
    $html = preg_replace('/javascript:/i', '', $html);
    $html = preg_replace('/data:/i', '', $html);
    
    return $html;
}