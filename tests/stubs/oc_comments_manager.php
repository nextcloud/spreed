<?php

namespace OC\Comments {

	use OCP\IDBConnection;

	class Manager implements \OCP\Comments\ICommentsManager {
		/** @var  IDBConnection */
		protected $dbConn;

		protected function normalizeDatabaseData(array $data): array {
		}
	}
}
