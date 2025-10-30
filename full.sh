#!/bin/bash

echo "=== SCHNELLE VERSION.PHP ANALYSE ==="

if [ -f "includes/version.php" ]; then
    echo "1. ZEILE 139 (Fehlerquelle):"
    sed -n '139p' includes/version.php
    
    echo
    echo "2. ZEILEN 135-145:"
    sed -n '135,145p' includes/version.php
    
    echo
    echo "3. ALLE 'function getDVDProfilerVersion' in der Datei:"
    grep -n "function getDVDProfilerVersion" includes/version.php
    
    echo
    echo "4. ALLE FUNKTIONSDEFINITIONEN mit Zeilennummern:"
    grep -n "^function\|^[[:space:]]*function" includes/version.php
    
    echo
    echo "5. ANZAHL der getDVDProfilerVersion Definitionen:"
    grep -c "function getDVDProfilerVersion" includes/version.php
    
    echo
    echo "6. DATEIGRÖSSE und letzte Änderung:"
    ls -la includes/version.php
    
else
    echo "❌ includes/version.php nicht gefunden!"
fi

echo "=== ENDE ==="