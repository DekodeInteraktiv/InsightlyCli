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

		$nginx_conf_files = $this->ssh->exec( 'cat /etc/nginx/sites.d/*' );
		$nginx_conf_files .= $this->ssh->exec( 'cat /etc/apache2/sites-enabled/*' );
		$nginx_conf_lines = explode( "\n", $nginx_conf_files );

		$found_domain   = false;
		$found_web_root = false;
		$port           = false;

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
			$return_value = $line;
		} else {
			$return_value = false;
		}

		return $return_value;

	}

	/**
	 * Returns username, password, DB name and host for database, if we can find it.
	 *
	 * @return array
	 */
	public function get_db_credentials() {
		$web_root = $this->get_web_root();

		$config_file = $this->ssh->exec( 'cat ' . $web_root . '/../config.php' );

		$config_file = explode( "\n", $config_file );

		foreach ( $config_file as &$line ) {
			$line = trim( $line );
			if ( preg_match( '/^#/', $line ) || preg_match( '/^\/\//', $line ) ) {
				$line = '';
			}
		}

		$config = [];

		foreach ( $config_file as $line ) {

			if ( preg_match( '/define\(\s?\'(.*)?\',\s?\'(.*)?(.*)\'\s?\)/', $line, $matches ) ) {

				$config[ $matches[1] ] = $matches[2];
			}
		}

		return $config;

	}

	/**
	 * Echos a database dump.
	 */
	public function show_database_dump_commands() {

		$config = $this->get_db_credentials();

		$required_fields = [
			'DB_HOST',
			'DB_USER',
			'DB_PASSWORD',
			'DB_NAME'
		];

		foreach ( $required_fields as $field ) {

			if ( ! isset( $config[ $field ] ) ) {
				throw new \Exception( $field . ' value not found in config file' );
			}
		}

		$project = $this->get_project();

		$climate = new CLImate();

		$climate->yellow( 'Carefully check these three commands and then run them from your prompt:' );

		$climate->green( $project->get_ssh_to_prod() . " 'mysqldump -h " . $config['DB_HOST'] . ' -u ' . $config['DB_USER'] . ' -p' . $config['DB_PASSWORD'] . ' ' . $config['DB_NAME'] . " > ~/" . $project->get_prod_domain() . '.sql\'' );

		$ssh_username_and_host = trim( str_replace( 'ssh', '', $project->get_ssh_to_prod() ) );

		$climate->green( 'scp -C ' . $ssh_username_and_host . ":~/" . $project->get_prod_domain() . '.sql .' );
		$climate->green( $project->get_ssh_to_prod() . " 'rm ~/" . $project->get_prod_domain() . '.sql\'' );

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