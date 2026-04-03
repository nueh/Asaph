<?php

class DB {
	public $sql;
	public $numQueries = 0;

	private $link = null;
	private $result = null;
	private $lastError = null;
	private $lastRowCount = 0;
	private $host, $db, $user, $pass;


	public function __construct( $host, $db, $user, $pass ) {
		$this->host = $host;
		$this->db = $db;
		$this->user = $user;
		$this->pass = $pass;
	}


	private function connect() {
		$dsn = "mysql:host={$this->host};dbname={$this->db};charset=utf8";
		$this->link = new PDO( $dsn, $this->user, $this->pass, [
			PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		]);
	}


	public function foundRows() {
		$r = $this->query( 'SELECT FOUND_ROWS() AS foundRows' );
		return $r[0]['foundRows'];
	}


	public function numRows() {
		return $this->lastRowCount;
	}


	public function affectedRows() {
		return $this->result ? $this->result->rowCount() : 0;
	}


	public function insertId() {
		return $this->link ? $this->link->lastInsertId() : 0;
	}


	public function query( $q, $params = [] ) {
		if( $this->link === null ) {
			$this->connect();
		}

		if( !is_array( $params ) ) {
			$params = array_slice( func_get_args(), 1 );
		}

		if( !empty( $params ) ) {
			$q = preg_replace_callback( '/:(\d+)/', function( $matches ) use ( $params ) {
				return $this->quote( $params[$matches[1] - 1] );
			}, $q );
		}

		$this->numQueries++;
		$this->sql = $q;
		$this->lastError = null;

		try {
			$this->result = $this->link->query( $q );
		} catch( PDOException $e ) {
			$this->lastError = $e->getMessage();
			return false;
		}

		if( $this->result->columnCount() === 0 ) {
			return true;
		}

		$rset = $this->result->fetchAll();
		$this->lastRowCount = count( $rset );
		return $rset;
	}


	public function getRow( $q, $params = [] ) {
		if( !is_array( $params ) ) {
			$params = array_slice( func_get_args(), 1 );
		}

		$r = $this->query( $q, $params );
		return array_shift( $r );
	}


	public function updateRow( $table, $idFields, $updateFields ) {
		$updateString = implode( ',', $this->quoteArray( $updateFields ) );
		$idString = implode( ' AND ', $this->quoteArray( $idFields ) );
		return $this->query( "UPDATE $table SET $updateString WHERE $idString" );
	}


	public function insertRow( $table, $insertFields ) {
		$insertString = implode( ',', $this->quoteArray( $insertFields ) );
		return $this->query( "INSERT INTO $table SET $insertString" );
	}


	public function getError() {
		if( $this->lastError ) {
			return "MySQL reports: '{$this->lastError}' on query\n" . $this->sql;
		}
		return false;
	}


	public function quote( $s ) {
		if( $this->link === null ) {
			$this->connect();
		}
		if( !isset($s) || $s === false ) {
			return 0;
		}
		else if( $s === true ) {
			return 1;
		}
		else if( is_numeric( $s ) ) {
			return $s;
		}
		else {
			return $this->link->quote( $s );
		}
	}


	public function quoteArray( &$fields ) {
		$r = [];
		foreach( $fields as $key => &$value ) {
			$r[] = "`$key`=" . $this->quote( $value );
		}
		return $r;
	}
}

?>
