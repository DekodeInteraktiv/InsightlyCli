<?php

namespace Dekode\InsightlyCli\Services;

use Dekode\InsightlyCli\Models\Project;
use phpseclib\Net\SSH2;
use phpseclib\Crypt\RSA;

class SSHService {

	private $project;

	public function __construct( Project $project ) {
		$ssh = $project->get_ssh_to_prod();
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

			if ( preg_match( '/<VirtualHost \*\:(\d{1,3})\>/', $line, $matches ) ) {
				$port = $matches[1];
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