<?php

namespace Dekode\InsightlyCli\Services;

use Dekode\InsightlyCli\Models\Project;
use phpseclib\Net\SSH2;
use phpseclib\Crypt\RSA;

class SSHService {

	const YES = 1;
	const NO = 2;
	const UNSURE = 3;

	private $project;

	/**
	 * SSHService constructor.
	 *
	 * @param Project $project
	 */
	public function __construct( Project $project ) {
		$ssh = $project->get_ssh_to_prod();
		if ( ! $ssh ) {
			throw new \Exception( 'No SSH command found in project' );
		}

		$ssh = str_replace( 'ssh', '', $ssh );
		$ssh = trim( $ssh );
		list( $username, $host ) = explode( '@', $ssh );

		$ssh = new SSH2( $host );
		$key = new RSA();
		$key->loadKey( file_get_contents( getenv( 'HOME' ) . '/.ssh/id_rsa' ) );
		if ( ! $ssh->login( $username, $key ) ) {
			throw new \Exception( 'Login failed' );
		}

		$this->set_project( $project );
		$this->ssh = $ssh;

	}

	/**
	 * Tries to find the web root of the project and returns it.
	 *
	 * @return bool|string
	 */
	public function guess_web_root() {

		if ( ! isset( $this->web_root ) ) {

			$web_server_conf_files = $this->ssh->exec( 'cat /etc/nginx/sites.d/*' );
			$web_server_conf_files .= $this->ssh->exec( 'cat /etc/apache2/sites-enabled/*' );
			$web_server_conf_lines = explode( "\n", $web_server_conf_files );

			$found_domain   = false;
			$found_web_root = false;
			$port           = false;
			$line           = false;

			foreach ( $web_server_conf_lines as $line ) {
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
		$web_root       = $this->guess_web_root();
		$uploads_folder = $this->ssh->exec( 'find ' . $web_root . ' -name uploads' );

		return trim( $uploads_folder );

	}

	/**
	 * Tries to find the path to the project's uploads folder.
	 *
	 * @return string
	 */
	public function get_uploads_url() {
		$web_root       = $this->guess_web_root();
		$uploads_folder = $this->get_uploads_folder();
		$uploads_path   = str_replace( $web_root, '', $uploads_folder );

		return $this->get_project()->get_prod_url() . $uploads_path;

	}

	/**
	 * Returns username, password, DB name and host for database, if we can find it.
	 *
	 * @return array
	 */
	public function get_db_details() {
		$web_root = $this->get_web_root();

		$output = $this->ssh->exec( 'cd ' . $web_root . ' && echo "print(\'DB_HOST: \' . DB_HOST . \"\n\" . \'DB_NAME: \' . DB_NAME . \"\n\" . \'DB_PASSWORD: \' . DB_PASSWORD . \"\n\" . \'DB_USER: \' . DB_USER);" | wp shell --allow-root;' );
		preg_match( '/DB_HOST: (.*)/', $output, $matches );
		$db_host = $matches[1];
		preg_match( '/DB_NAME: (.*)/', $output, $matches );
		$db_name = $matches[1];
		preg_match( '/DB_PASSWORD: (.*)/', $output, $matches );
		$db_password = $matches[1];
		preg_match( '/DB_USER: (.*)/', $output, $matches );
		$db_user = $matches[1];

		$config['DB_USER']     = $db_user;
		$config['DB_HOST']     = $db_host;
		$config['DB_PASSWORD'] = $db_password;
		$config['DB_NAME']     = $db_name;


		$config['table_prefix'] = $this->get_table_prefix();

		return $config;

	}

	/**
	 * Tries to find the site's table prefix and returns it.
	 *
	 * @return bool
	 */
	public function get_table_prefix() {
		$web_root = $this->get_web_root();

		$command = 'cd ' . $web_root . ' && echo \'global $wpdb; print("TABLE PREFIX: " . $wpdb->base_prefix);\' | wp shell --allow-root';
		$output  = $this->ssh->exec( $command );

		preg_match( '/TABLE PREFIX: (.*)/', $output, $matches );

		if ( isset( $matches[1] ) ) {
			return $matches[1];
		} else {
			return false;
		}

	}


	/**
	 * Checks if plugin is installed
	 *
	 * @param $slug
	 *
	 * @return bool
	 */
	public function plugin_is_installed( $slug ) {
		$web_root = $this->get_web_root();
		$result   = $this->ssh->exec( 'cd ' . $web_root . ' && find | grep plugins | grep ' . $slug );

		return trim( $result ) != '';
	}

	/**
	 * Checks if site is multisite
	 *
	 * @return int
	 */
	public function is_multisite() {
		$web_root = $this->get_web_root();

		$output = $this->ssh->exec( 'cd ' . $web_root . ' && echo "print(\'MULTISITE: \' . (is_multisite() ? \'1\' : \'0\'));" | wp shell --allow-root' );

		preg_match( '/MULTISITE: (\d)/', $output, $matches );

		if ( isset( $matches[1] ) ) {
			if ( $matches[1] == 1 ) {
				return self::YES;
			} else {
				return self::NO;
			}
		} else {
			return self::UNSURE;
		}

	}

	/**
	 * Returns the URL of the main site in a multi-site installation.
	 *
	 * @return |null
	 */
	public function get_main_site_url_in_multisite() {
		$web_root = $this->get_web_root();

		$output = $this->ssh->exec( 'cd ' . $web_root . ' && echo "print(\'MAIN_URL: \' . network_site_url());" | wp shell --allow-root' );

		preg_match( '/MAIN_URL: (.*)/', $output, $matches );

		if ( isset( $matches[1] ) ) {
			return $matches[1];
		}

		return null;

	}


	/**
	 * @param $command
	 *
	 * @return string
	 */
	public function run_raw_command( $command ) {
		return $this->ssh->exec( $command );
	}

	/**
	 * @return bool|mixed|string
	 */
	protected function get_web_root() {
		$web_root = $this->get_project()->get_web_root();

		if ( ! $web_root ) {
			$web_root = $this->guess_web_root();
		}

		return $web_root;
	}

	/**
	 * Will try to find the OS the site runs on and return it.
	 *
	 * @return string
	 */
	public function get_os() {
		return trim( $this->ssh->exec( 'cat /etc/issue' ) );

	}

	/**
	 * Returns the username of the linux user who owns the web root.
	 *
	 * @return string
	 */
	public function get_file_owner() {
		$owner = $this->ssh->exec( "stat -c ' % U' " . $this->guess_web_root() );

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