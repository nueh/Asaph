<?php

class DB {
	public $sql;
	public $numQueries = 0;

	private $config;
	private $driver;
	private $link = null;
	private $result = null;
	private $lastError = null;
	private $lastRowCount = 0;


	public function __construct( $config ) {
		$this->config = $config;
		$this->driver = $config['driver'] ?? 'mysql';
	}


	private function connect() {
		$options = [
			PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		];

		if( $this->driver === 'sqlite' ) {
			$path = ASAPH_PATH . ($this->config['path'] ?? 'data/asaph.db');
			$this->link = new PDO( 'sqlite:' . $path, null, null, $options );
			$this->link->exec( 'PRAGMA journal_mode=WAL' );
			$this->link->exec( 'PRAGMA foreign_keys=ON' );
		} else {
			$dsn = "mysql:host={$this->config['host']};dbname={$this->config['database']};charset=utf8";
			$this->link = new PDO( $dsn, $this->config['user'], $this->config['password'], $options );
		}
	}


	public function driver() {
		return $this->driver;
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


	// Uses standard INSERT syntax compatible with both MySQL and SQLite
	public function insertRow( $table, $insertFields ) {
		$cols = '`' . implode( '`,`', array_keys($insertFields) ) . '`';
		$vals = implode( ',', array_map( fn($v) => $this->quote($v), array_values($insertFields) ) );
		return $this->query( "INSERT INTO $table ($cols) VALUES ($vals)" );
	}


	public function tableExists( $name ) {
		if( $this->link === null ) {
			$this->connect();
		}
		if( $this->driver === 'sqlite' ) {
			$r = $this->query( "SELECT name FROM sqlite_master WHERE type='table' AND name=:1", $name );
		} else {
			$r = $this->query( "SHOW TABLES LIKE :1", $name );
		}
		return !empty( $r );
	}


	public function getError() {
		if( $this->lastError ) {
			return "Database reports: '{$this->lastError}' on query\n" . $this->sql;
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
