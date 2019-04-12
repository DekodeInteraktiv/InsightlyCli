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
	 * @param  Project $project
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

			if ( ! trim( $this->get_project()->get_prod_domain() ) ) {
				throw new Exception( 'No production domain on site.' );
			}

			$web_server_conf_files = $this->ssh->exec( 'cat /etc/nginx/sites.d/*' );
			$web_server_conf_files .= $this->ssh->exec( 'cat /etc/apache2/sites-enabled/*' );
			$web_server_conf_lines = explode( "\n", $web_server_conf_files );

			$found_domain   = false;
			$found_web_root = false;
			$port           = false;
			$line           = false;

			foreach ( $web_server_conf_lines as $line ) {

				// Skip commented out lines
				if ( strpos( trim( $line ), '#' ) === 0 ) {
					continue;
				}

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
		$web_root = $this->guess_web_root();

		print( $web_root . "\n" );
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
		$wp_cli_command = $this->get_wp_cli_command();

		$web_root = $this->get_web_root();

		// Try both with and without --url flag.
		$commands[] = 'cd ' . $web_root . ' && echo "print(\'DB_HOST: \' . DB_HOST . \"\n\" . \'DB_NAME: \' . DB_NAME . \"\n\" . \'DB_PASSWORD: \' . DB_PASSWORD . \"\n\" . \'DB_USER: \' . DB_USER);" | ' . $wp_cli_command . ' shell ;';

		foreach ( $commands as $command ) {

			$output = $this->ssh->exec( $command );

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

			if ( $config['DB_USER'] ) {
				break;
			}
		}

		$config['table_prefix'] = $this->get_table_prefix();

		return $config;

	}

	/**
	 * @return bool
	 */
	public function wp_cli_is_installed() {
		$web_root       = $this->get_web_root();
		$wp_cli_command = $this->get_wp_cli_command();

		$output = $this->ssh->exec( 'cd ' . $web_root . ' && ' . $wp_cli_command . ';' );

		if ( strpos( $output, 'wp: command not found' ) !== false ) {
			return false;
		} else {
			return true;
		}


	}

	/**
	 * Tries to find the site's table prefix and returns it.
	 *
	 * @return bool
	 */
	public function get_table_prefix() {
		$web_root       = $this->get_web_root();
		$wp_cli_command = $this->get_wp_cli_command();

		$command = 'cd ' . $web_root . ' && echo \'global $wpdb; print("TABLE PREFIX: " . $wpdb->base_prefix);\' | ' . $wp_cli_command . ' shell';
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
		$web_root       = $this->get_web_root();
		$wp_cli_command = $this->get_wp_cli_command();

		$output = $this->ssh->exec( 'cd ' . $web_root . ' && echo "print(\'MULTISITE: \' . (is_multisite() ? \'1\' : \'0\'));" | ' . $wp_cli_command . ' shell' );

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
		$web_root       = $this->get_web_root();
		$wp_cli_command = $this->get_wp_cli_command();

		$output = $this->ssh->exec( 'cd ' . $web_root . ' && echo "print(\'MAIN_URL: \' . network_site_url());" | ' . $wp_cli_command . ' shell' );

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
	 * @return string
	 */
	protected function get_absolute_web_root() {
		$web_root = $this->get_web_root();

		if ( strpos( $web_root, '~' ) === 0 ) {
			$absolute_web_root = $this->ssh->exec( 'cd ' . $web_root . ' && pwd' );
		} else {
			$absolute_web_root = $web_root;
		}

		return trim( $absolute_web_root );
	}

	/**
	 * @return string
	 */
	protected function get_wp_cli_command() {
		$wp_core_path = $this->get_path_of_wp_core();

		$cmd = 'wp  --allow-root ';

		if ( $wp_core_path ) {
			$cmd .= ' --path="' . $wp_core_path . '"';
		}

		return $cmd;

	}

	/**
	 * @return string
	 */
	protected function get_path_of_wp_core() {
		$web_root = $this->get_absolute_web_root();

		$cmd = 'cd ' . $web_root . ' && ls';

		$content = $this->ssh->exec( $cmd );

		$files = explode( "\n", $content );

		$wp_path = '';

		foreach ( $files as $file ) {
			if ( trim( $file ) == 'wp' ) {
				$wp_path = $file;
			}
		}

		if ( $wp_path ) {
			$wp_path = $web_root . '/' . $wp_path;
			$wp_path = $this->ssh->exec( 'cd ' . $wp_path . ' && pwd -P' );
		} else {
			$wp_path = $web_root;
		}

		return trim( $wp_path );
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
	 * Returns a list of all users.
	 *
	 * @return string
	 */
	public function get_wp_users() {
		$web_root = $this->get_web_root();
		$headers  = [];

		$wp_cli_command = $this->get_wp_cli_command();

		$output = $this->ssh->exec( 'cd ' . $web_root . ' && ' . $wp_cli_command . ' user list' );
		$output = explode( "\n", $output );

		foreach ( $output as $line ) {
			$properties = explode( "\t", $line );

			if ( count( $properties ) < 4 ) {
				continue;
			}


			if ( $properties[0] == 'ID' ) {
				$headers = $properties;
			} else {
				$user = array();
				foreach ( $properties as $index => $property ) {
					if ( trim( $property ) ) {
						$user[ $headers[ $index ] ] = trim( $property );
					}
				}
				if ( count( $user ) ) {
					$users[] = $user;
				}
			}
		}

		return $users;

	}

	/**
	 * @return mixed
	 */
	public function get_memory_usage() {
		$output = $this->ssh->exec( 'free -b' );

		$lines = explode( "\n", $output );
		unset( $lines[0] );

		foreach ( $lines as $line ) {
			$properties = explode( " ", $line );
			$properties = array_filter( $properties );
			$properties = array_values( $properties );

			if ( count( $properties ) > 2 ) {
				$label = $properties[0];
				$total = $properties[1];
				$used  = $properties[2];

				$label = strtolower( $label );
				$label = str_replace( ':', '', $label );

				$return_values[ $label ]['total']       = $total;
				$return_values[ $label ]['total_human'] = $this->human_filesize( $total );
				$return_values[ $label ]['used']        = $used;
				$return_values[ $label ]['used_human']  = $this->human_filesize( $used );
			}
		}

		return $return_values;

	}

	/**
	 * @return array
	 */
	public function get_cpu_load() {
		$output = $this->ssh->exec( 'top -b -n 1' );

		$output = explode( "\n", $output );

		foreach ( $output as $line ) {
			if ( preg_match( '/^\%Cpu\(s\).*?([\d\.]*?) id/', $line, $matches ) ) {

				$return_array         = [];
				$return_array['idle'] = $matches[1];
				$return_array['used'] = 100 - $matches[1];

				break;
			}
		}

		return $return_array;
	}

	/**
	 * @return array
	 */
	public function get_disk_space() {
		$disks = [];

		$output = $this->ssh->exec( 'df -B1' );

		$output = explode( "\n", $output );

		foreach ( $output as $line ) {
			$properties = explode( " ", $line );

			if ( count( $properties ) < 3 ) {
				continue;
			}

			foreach ( $properties as $index => $property ) {
				if ( trim( $property ) === '' ) {
					unset( $properties[ $index ] );
				}
			}

			$properties = array_values( $properties );

			if ( ! isset( $headers ) ) {
				$headers = $properties;
				unset( $headers[6] );

			} else {
				$disk = [];
				foreach ( $headers as $index => $header ) {
					$disk[ $header ] = $properties[ $index ];
				}

				$disks[] = $disk;
			}
		}

		return $disks;

	}

	/**
	 * @param      $bytes
	 * @param  int $decimals
	 *
	 * @return string
	 */
	public function human_filesize( $bytes, $decimals = 2 ) {
		$size   = array( 'B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB' );
		$factor = floor( ( strlen( $bytes ) - 1 ) / 3 );

		return sprintf( "%.{$decimals}f", $bytes / pow( 1024, $factor ) ) . @$size[ $factor ];
	}

	/**
	 * @return mixed
	 */
	public function get_project(): ?Project {
		return $this->project;
	}

	/**
	 * @param  mixed $project
	 */
	public function set_project( Project $project ) {
		$this->project = $project;
	}


}