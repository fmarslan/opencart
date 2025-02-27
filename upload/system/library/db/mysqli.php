<?php
namespace Opencart\System\Library\DB;
/**
 * Class MySQLi
 *
 * @package
 */
class MySQLi {
	/**
	 * @var object|\mysqli|null
	 */
	private object|null $connection;

	/**
	 * Constructor
	 *
	 * @param    string  $hostname
	 * @param    string  $username
	 * @param    string  $password
	 * @param    string  $database
	 * @param    string  $port
	 */
	public function __construct(string $hostname, string $username, string $password, string $database, string $port = '', string $ssl_key = '', string $ssl_cert = '', string $ssl_ca = '') {
		if (!$port) {
			$port = '3306';
		}

		try {			
			$this->connection =  mysqli_init();			
			if (!empty($ssl_key) || !empty($ssl_cert) || !empty($ssl_ca)) {
				$temp_ssl_key_file  = null;
				$temp_ssl_cert_file = null;
				$temp_ssl_ca_file   = null;

				if ($ssl_key) {
					$temp_ssl_key_file = tempnam(sys_get_temp_dir(), 'mysqli_key_');
					$handle = fopen($temp_ssl_key_file, 'w');
					fwrite($handle,'-----BEGIN CERTIFICATE-----' . PHP_EOL . $ssl_key . PHP_EOL . '-----END CERTIFICATE-----');
					fclose($handle);
				}
				
				if ($ssl_cert) {
					$temp_ssl_cert_file = tempnam(sys_get_temp_dir(), 'mysqli_cert_');
					$handle = fopen($temp_ssl_cert_file, 'w');
					fwrite($handle,'-----BEGIN CERTIFICATE-----' . PHP_EOL . $ssl_cert . PHP_EOL . '-----END CERTIFICATE-----');
					fclose($handle);
				}
				
				if ($ssl_ca) {
					$temp_ssl_ca_file = tempnam(sys_get_temp_dir(), 'mysqli_ca_');
					$handle = fopen($temp_ssl_ca_file, 'w');
					fwrite($handle,'-----BEGIN CERTIFICATE-----' . PHP_EOL . $ssl_ca . PHP_EOL . '-----END CERTIFICATE-----');
					fclose($handle);
				}

				$this->connection->ssl_set($temp_ssl_key_file ?: null, $temp_ssl_cert_file ?: null, $temp_ssl_ca_file ?: null, null, null);				
				$this->connection->real_connect($hostname, $username, $password, $database, $port, null, MYSQLI_CLIENT_SSL);
			} else {
				$this->connection->real_connect($hostname, $username, $password, $database, $port, null);
			}
			
			$this->connection->set_charset('utf8mb4');
			
			$this->query("SET SESSION sql_mode = 'NO_ZERO_IN_DATE,NO_ENGINE_SUBSTITUTION'");
			$this->query("SET FOREIGN_KEY_CHECKS = 0");

			// Sync PHP and DB time zones
			$this->query("SET `time_zone` = '" . $this->escape(date('P')) . "'");
		} catch (\mysqli_sql_exception $e) {
			throw new \Exception('Error: Could not make a database link using ' . $username . '@' . $hostname . '!<br/>Message: ' . $e->getMessage());
		}
	}

	/**
	 * Query
	 *
	 * @param    string  $sql
	 *
	 * @return   bool|object
	 */
	public function query(string $sql): bool|object {
		try {
			$query = $this->connection->query($sql);

			if ($query instanceof \mysqli_result) {
				$data = [];

				while ($row = $query->fetch_assoc()) {
					$data[] = $row;
				}

				$result = new \stdClass();
				$result->num_rows = $query->num_rows;
				$result->row = isset($data[0]) ? $data[0] : [];
				$result->rows = $data;

				$query->close();

				unset($data);

				return $result;
			} else {
				return true;
			}
		} catch (\mysqli_sql_exception $e) {
			throw new \Exception('Error: ' . $this->connection->error  . '<br/>Error No: ' . $this->connection->errno . '<br/>' . $sql);
		}
	}

	/**
	 * Escape
	 *
	 * @param    string  value
	 *
	 * @return   string
	 */
	public function escape(string $value): string {
		return $this->connection->real_escape_string($value);
	}
	
	/**
	 * countAffected
	 *
	 * @return   int
	 */
	public function countAffected(): int {
		return $this->connection->affected_rows;
	}

	/**
	 * getLastId
	 *
	 * @return   int
	 */
	public function getLastId(): int {
		return $this->connection->insert_id;
	}
	
	/**
	 * isConnected
	 *
	 * @return   bool
	 */
	public function isConnected(): bool {
		return $this->connection;
	}

	/**
	 * Destructor
	 *
	 * Closes the DB connection when this object is destroyed.
	 *
	 */
	public function __destruct() {
		if ($this->connection) {
			$this->connection->close();

			$this->connection = null;
		}
	}
}
