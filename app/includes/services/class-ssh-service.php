<?php

namespace Dekode\InsightlyCli\Services;

use Dekode\InsightlyCli\Models\Project;
use GuzzleHttp\Client;
use League\CLImate\CLImate;
use phpseclib\Net\SSH2;
use phpseclib\Crypt\RSA;

class SSHService {

	private $project;

	/**
	 * SSHService constructor.
	 *
	 * @param Project $project
	 */
	public function __construct( Project $project ) {
		$ssh = $project->get_ssh_to_prod();
		if ( ! $ssh ) {
			throw new Exception( 'No SSH command found in project' );
		}

		$ssh = str_replace( 'ssh', '', $ssh );
		$ssh = trim( $ssh );
		list( $username, $host ) = explode( '@', $ssh );

		$ssh = new SSH2( $host );
		$key = new RSA();
		$key->loadKey( file_get_contents( getenv( 'HOME' ) . '/.ssh/id_rsa' ) );
		if ( ! $ssh->login( $username, $key ) ) {
			throw new Exception( 'Login failed' );
		}

		$this->set_project( $project );
		$this->ssh = $ssh;

	}

	/**
	 * Tries to find the web root of the project and returns it.
	 *
	 * @return bool|string
	 */
	public function get_web_root() {

		if ( ! isset( $this->web_root ) ) {

			$nginx_conf_files = $this->ssh->exec( 'cat /etc/nginx/sites.d/*' );
			$nginx_conf_files .= $this->ssh->exec( 'cat /etc/apache2/sites-enabled/*' );
			$nginx_conf_lines = explode( "\n", $nginx_conf_files );

			$found_domain   = false;
			$found_web_root = false;
			$port           = false;
			$line           = false;

			foreach ( $nginx_conf_lines as $line ) {
				// Nginx
				if ( preg_match( '/listen (\d{2,3})/', $line, $matches ) ) {
					$port = $matches[1];
				}

				// Apache
				if ( preg_match( '/<VirtualHost \*\:(\d{1,3})\>/', $line, $matches ) ) {
					$port = $matches[1];
				}

				if ( strpos( $line, ' ' . $this->get_project()->get_prod_domain() . ';' ) ) {
					$found_domain = true;
				}

				if ( strpos( $line, ' ' . $this->get_project()->get_prod_domain() . ' ' ) ) {
					$found_domain = true;
				}

				if ( strpos( $line, ' ' . $this->get_project()->get_prod_domain() ) ) {
					$found_domain = true;
				}


				if ( $found_domain && $port == 443 && ( strpos( $line, 'root' ) || strpos( $line, 'DocumentRoot' ) ) && strpos( $line, '#' ) === false ) {

					$line           = str_replace( 'root', '', $line );
					$line           = str_replace( 'DocumentRoot', '', $line );
					$line           = str_replace( ';', '', $line );
					$line           = trim( $line );
					$found_web_root = true;


					break;
				}


			}

			if ( $found_web_root ) {
				$this->web_root = $line;
			} else {
				$this->web_root = false;
			}
		}

		return $this->web_root;

	}

	/**
	 * Tries to find the path to the project's uploads folder.
	 *
	 * @return string
	 */
	public function get_uploads_folder() {
		$web_root       = $this->get_web_root();
		$uploads_folder = $this->ssh->exec( 'find ' . $web_root . ' -name uploads' );

		return trim( $uploads_folder );

	}

	/**
	 * Tries to find the path to the project's uploads folder.
	 *
	 * @return string
	 */
	public function get_uploads_url() {
		$web_root       = $this->get_web_root();
		$uploads_folder = $this->get_uploads_folder();
		$uploads_path   = str_replace( $web_root, '', $uploads_folder );

		return $this->get_project()->get_prod_url() . $uploads_path;

	}

	/**
	 * Returns username, password, DB name and host for database, if we can find it.
	 *
	 * @return array
	 */
	public function get_db_credentials() {
		$web_root = $this->get_web_root();

		$config_file = $this->ssh->exec( 'cat ' . $web_root . '/../config.php' );
		$config_file .= $this->ssh->exec( 'cat ' . $web_root . '/../.env' );

		$config_file = explode( "\n", $config_file );


		foreach ( $config_file as &$line ) {
			$line = trim( $line );
			if ( preg_match( '/^#/', $line ) || preg_match( '/^\/\//', $line ) ) {
				$line = '';
			}
		}

		$config = [ 'DB_HOST' => 'localhost' ];

		foreach ( $config_file as $line ) {

			if ( preg_match( '/define\(\s?\'(.*)?\',\s?\'(.*)?(.*)\'\s?\)/', $line, $matches ) ) {
				$config[ $matches[1] ] = $matches[2];
			}
			if ( preg_match( '/^([A-Z_]*)\=(.*)$/', $line, $matches ) ) {

				$config[ $matches[1] ] = $matches[2];
			}

		}

		return $config;

	}

	/**
	 * Returns the username of the linux user who owns the web root.
	 *
	 * @return string
	 */
	public function get_file_owner() {
		$owner = $this->ssh->exec( "stat -c '%U' " . $this->get_web_root() );

		return trim( $owner );
	}

	/**
	 * @return mixed
	 */
	public function get_project(): ?Project {
		return $this->project;
	}

	/**
	 * @param mixed $project
	 */
	public function set_project( Project $project ) {
		$this->project = $project;
	}


}