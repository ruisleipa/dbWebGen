<?php
/*
    Processes MODE_FILE requests,  which deliver a file uploaded using a T_UPLOAD field
    by providing the primary key(s) of the record in the table. This allows file name
    independent retrieval of uploaded files.
    
    Following URL parameters are required:
        * table: name of the DB table that holds the file info
        * field: name of the field in table that holds the file name
        * primary key values (depends on primary keys of table)
    Example:
        /?mode=file&table=uploads&field=filename&id=27 
*/

// ============================================================================
class FileRetrieval {
// ============================================================================

    protected   $table,
                $field,
                $primaryKeys;
    
    // ------------------------------------------------------------------------
    protected function __construct(
        $table,
        $field,
        $primaryKeys
    ) {
    // ------------------------------------------------------------------------
        $this->table = $table;
        $this->field = $field;
        $this->primaryKeys = $primaryKeys;
    }

    // ------------------------------------------------------------------------
    protected function redirect(
    ) {
    // ------------------------------------------------------------------------
        global $TABLES;
        if(!isset($TABLES[$this->table]))
            return proc_error(l10n('error.invalid-params'));
        $table = $TABLES[$this->table];
        if(!isset($table['fields'][$this->field]))
            return proc_error(l10n('error.invalid-params'));
        $field = $table['fields'][$this->field];
        if($field['type'] !== T_UPLOAD)
            return proc_error(l10n('error.invalid-params'));
        if(count($this->primaryKeys) !== count($table['primary_key']['columns']))
            return proc_error(l10n('error.invalid-params'));
        foreach($this->primaryKeys as $k => $v) {
            if(!in_array($k, $table['primary_key']['columns']))
                return proc_error(l10n('error.invalid-params'));
        }
        $params = [];
        $sql = sprintf(
            'select %s from %s where %s',
            db_esc($this->field),
            db_esc($this->table),
            implode(' and ', array_map(function($v, $k) use(&$params) {
                $params[] = $v;
                return sprintf('%s = ?', db_esc($k));
            }, array_values($this->primaryKeys), array_keys($this->primaryKeys)))
        );
        $db = db_connect();
        if($db === false)
			return proc_error(l10n('error.db-connect'));
		$stmt = $db->prepare($sql);
		if($stmt === false)
			return proc_error(l10n('error.db-prepare'), $db);
		if(false === $stmt->execute($params))
            return proc_error(l10n('error.db-execute'), $stmt);
        $fileName = $stmt->fetchColumn();
        if($fileName === false)
            return proc_error(l10n('error.invalid-params'));
        $store_folder = str_replace("\\", '/', $field['location']);
        if(substr($store_folder, -1) !== '/')
            $store_folder .= '/';
        header('Location: ' . $store_folder . $fileName);
        return true;
    }

    // ------------------------------------------------------------------------
    public static function processRequest(
    ) {
    // ------------------------------------------------------------------------
        $primaryKeys = [];
        foreach($_GET as $k => $v) {
            switch ($k) {
                case 'table': $table = $v; break;
                case 'field': $field = $v; break;
                case 'mode': break;
                default: $primaryKeys[$k] = $v;
            }
        }
        $f = new FileRetrieval($table, $field, $primaryKeys);
        return $f->redirect();
    }
}