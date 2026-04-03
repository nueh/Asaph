<?php

/* The Asaph class hosts all functions to select and process posts for
the front page.

To integrate Asaph within other systems, just define your ASAPH_PATH
and include this file. You can then create a new Asaph object and fetch
the newest $numberOfPosts posts to an array.

$asaph = new Asaph( $numberOfPosts );
$asaphPosts = $asaph->getPosts( $pageToFetch ); */

require_once( ASAPH_PATH.'lib/asaph_config.class.php' );
require_once( ASAPH_PATH.'lib/db.class.php' );

class Asaph {
	protected $db = null;
	protected $postsPerPage = 0;
	protected $currentPage = 0;

	public function __construct( $postsPerPage = 25 ) {
		$this->postsPerPage = $postsPerPage;
		$this->db = new DB( Asaph_Config::$db );
	}


	public function getPosts( $page ) {
		$this->currentPage = abs( intval($page) );

		// Separate COUNT query works on both MySQL and SQLite
		$count = $this->db->query( 'SELECT COUNT(*) AS count FROM '.ASAPH_TABLE_POSTS );
		$this->totalPosts = (int) $count[0]['count'];

		$posts = $this->db->query(
			'SELECT
				p.created,
				p.id, p.source, p.thumb, p.image, p.title, u.name AS user
			FROM
				'.ASAPH_TABLE_POSTS.' p
			LEFT JOIN '.ASAPH_TABLE_USERS.' u
				ON u.id = p.userId
			ORDER BY
				p.created DESC
			LIMIT
				:1, :2',
			$this->currentPage * $this->postsPerPage,
			$this->postsPerPage
		);

		foreach( array_keys($posts) as $i ) {
			$this->processPost( $posts[$i] );
		}

		return $posts;
	}


	public function getPages() {
		$pages = array(
			'current' => 1,
			'total' => 1,
			'prev' => false,
			'next' => false,
		);
		if( $this->totalPosts > 0 ) {
			$pages['current'] = $this->currentPage + 1;
			$pages['total'] = ceil($this->totalPosts / $this->postsPerPage );
			if( $this->currentPage > 0 ) {
				$pages['prev'] = $this->currentPage;
			}
			if( $this->totalPosts > $this->postsPerPage * $this->currentPage + $this->postsPerPage ) {
				$pages['next'] = $this->currentPage + 2;
			}
		}

		return $pages;
	}


	protected function processPost( &$post ) {
		// Normalise created to a Unix timestamp regardless of driver
		$post['created'] = is_numeric( $post['created'] )
			? (int) $post['created']
			: strtotime( $post['created'] );

		$urlParts = parse_url( $post['source'] );
		$datePath = date( 'Y/m/', $post['created'] );
		$post['sourceDomain'] = $urlParts['host'];
		$post['source'] = htmlspecialchars( $post['source'] );
		$post['title'] = htmlspecialchars( $post['title'] );

		if( $post['thumb'] && $post['image'] ) {
			$post['thumb'] =
				Asaph_Config::$absolutePath
				.Asaph_Config::$images['thumbPath']
				.$datePath
				.$post['thumb'];

			$post['image'] =
				Asaph_Config::$absolutePath
				.Asaph_Config::$images['imagePath']
				.$datePath
				.$post['image'];
		}
	}
}

?>
