## 1. Download/install drone binary:
##    curl -L https://github.com/harness/drone-cli/releases/latest/download/drone_linux_amd64.tar.gz | tar zx
## 2. Adjust the matrix as wished
## 3. Run: ./drone  jsonnet --stream --format yml
## 4. Commit the result

local Pipeline(test_set, database, services) = {
	kind: "pipeline",
	name: "int-"+database+"-"+test_set,
	services: services,
	steps: [
		{
			name: "integration-"+test_set,
			image: "ghcr.io/nextcloud/continuous-integration-php8.2:latest",
			environment: {
				APP_NAME: "spreed",
				CORE_BRANCH: "master",
				GUESTS_BRANCH: "master",
				NOTIFICATIONS_BRANCH: "master",
				DATABASEHOST: database
			},
			commands: [
				"bash tests/drone-run-integration-tests.sh || exit 0",
				"composer --version",
				"composer self-update --2",
				"wget https://raw.githubusercontent.com/nextcloud/travis_ci/master/before_install.sh",
				"bash ./before_install.sh $APP_NAME $CORE_BRANCH $DATABASEHOST",
				"cd ../server",
				"cd apps/$APP_NAME",
				"composer install --no-dev",
				"cd ../..",
				"./occ app:enable $APP_NAME",
				"git clone --depth 1 -b $NOTIFICATIONS_BRANCH https://github.com/nextcloud/notifications apps/notifications",
				"./occ app:enable notifications"
			] + (
				if test_set == "conversation" || test_set == "conversation-2" then [
					"git clone --depth 1 -b $GUESTS_BRANCH https://github.com/nextcloud/guests apps/guests"
				] else []
			) + [
				"cd apps/$APP_NAME/tests/integration/",
				"bash run.sh features/chat/mentions.feature:9"
			]
		}
	],
	trigger: {
		branch: [
			"master",
			"stable*"
		],
		event: (
			if database == "pgsql" then ["pull_request", "push"] else ["push"]
		)
	}
};

local PipelineSQLite(test_set) = Pipeline(
	test_set,
	"sqlite",
	[
		{
			name: "cache",
			image: "ghcr.io/nextcloud/continuous-integration-redis:latest"
		}
	]
);

local PipelineMySQL(test_set) = Pipeline(
	test_set,
	"mysql",
	[
		{
			name: "cache",
			image: "ghcr.io/nextcloud/continuous-integration-redis:latest"
		},
		{
			name: "mysql",
			image: "ghcr.io/nextcloud/continuous-integration-mariadb-10.4:10.4",
			environment: {
				MYSQL_ROOT_PASSWORD: "owncloud",
				MYSQL_USER: "oc_autotest",
				MYSQL_PASSWORD: "owncloud",
				MYSQL_DATABASE: "oc_autotest"
			},
			command: [
				"--innodb_large_prefix=true",
				"--innodb_file_format=barracuda",
				"--innodb_file_per_table=true",
				"--sql-mode=ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION"
			],
			tmpfs: [
				"/var/lib/mysql"
			]
		}
	]
);

local PipelinePostgreSQL(test_set) = Pipeline(
	test_set,
	"pgsql",
	[
		{
			name: "cache",
			image: "ghcr.io/nextcloud/continuous-integration-redis:latest"
		},
		{
			name: "pgsql",
			image: "ghcr.io/nextcloud/continuous-integration-postgres-13:postgres-13",
			environment: {
				POSTGRES_USER: "oc_autotest",
				POSTGRES_DB: "oc_autotest",
				POSTGRES_HOST_AUTH_METHOD: "trust",
				POSTGRES_PASSWORD: "owncloud"
			},
			tmpfs: [
				"/var/lib/postgresql/data"
			]
		}
	]
);


[
	PipelinePostgreSQL("chat"),
]
