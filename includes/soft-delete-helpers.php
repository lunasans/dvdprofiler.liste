<?php
/**
 * Soft Delete Helper Functions
 * 
 * Diese Funktionen sollten in bootstrap.php eingebunden werden
 */

/**
 * Fügt deleted-Filter zu WHERE-Clause hinzu
 * 
 * @param bool $includeDeleted Gelöschte Filme einbeziehen?
 * @return string WHERE-Clause Teil
 */
function getDeletedFilter($includeDeleted = false) {
    if ($includeDeleted) {
        return ''; // Keine Filterung
    }
    return 'deleted = 0';
}

/**
 * Kombiniert WHERE-Clauses mit deleted-Filter
 * 
 * @param array $conditions Bestehende WHERE-Bedingungen
 * @param bool $includeDeleted Gelöschte Filme einbeziehen?
 * @return string Komplette WHERE-Clause oder leerer String
 */
function buildWhereWithDeleted(array $conditions = [], $includeDeleted = false) {
    $deletedFilter = getDeletedFilter($includeDeleted);
    
    if (!empty($deletedFilter)) {
        $conditions[] = $deletedFilter;
    }
    
    if (empty($conditions)) {
        return '';
    }
    
    return 'WHERE ' . implode(' AND ', $conditions);
}

/**
 * Gibt SQL-Fragment für Standard-Film-Filter zurück
 * Filtert automatisch gelöschte Filme
 * 
 * @param bool $includeDeleted Gelöschte Filme einbeziehen?
 * @return string "WHERE deleted = 0" oder ""
 */
function whereNotDeleted($includeDeleted = false) {
    if ($includeDeleted) {
        return '';
    }
    return 'WHERE deleted = 0';
}
?>