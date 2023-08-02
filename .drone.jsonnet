## 1. Download/install drone binary:
##        curl -L https://github.com/harness/drone-cli/releases/latest/download/drone_linux_amd64.tar.gz | tar zx
## 2. Adjust the matrix as wished
## 3. Transform jsonnet to yml:
##        ./drone  jsonnet --stream --format yml
## 4. Export your drone token and the server:
##        export DRONE_TOKEN=… export DRONE_SERVER=https://drone.nextcloud.com
## 5. Sign off the changes:
##        ./drone sign nextcloud/spreed --save
## 6. Copy the new signature from .drone.yml to `hmac` field in this file
## 7. Transform jsonnet to yml again (to transfer the signature correctly):
##        ./drone  jsonnet --stream --format yml
## 8. Commit the result

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
				CORE_BRANCH: "stable27",
				GUESTS_BRANCH: "master",
				CSB_BRANCH: "main",
				NOTIFICATIONS_BRANCH: "stable27",
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
				"./occ app:enable --force notifications",
				"git clone --depth 1 -b $GUESTS_BRANCH https://github.com/nextcloud/guests apps/guests",
				"./occ app:enable --force guests",
				"git clone --depth 1 -b $CSB_BRANCH https://github.com/nextcloud/call_summary_bot apps/call_summary_bot",
				"./occ app:enable --force call_summary_bot",
				"cd apps/$APP_NAME/tests/integration/",
				"bash run.sh features/"+test_set
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
	PipelineSQLite("callapi"),
	PipelineSQLite("chat"),
	PipelineSQLite("chat-2"),
	PipelineSQLite("command"),
	PipelineSQLite("conversation"),
	PipelineSQLite("conversation-2"),
	PipelineSQLite("federation"),
	PipelineSQLite("integration"),
	PipelineSQLite("sharing"),
	PipelineSQLite("sharing-2"),

	PipelineMySQL("callapi"),
	PipelineMySQL("chat"),
	PipelineMySQL("chat-2"),
	PipelineMySQL("command"),
	PipelineMySQL("conversation"),
	PipelineMySQL("conversation-2"),
	PipelineMySQL("federation"),
	PipelineMySQL("integration"),
	PipelineMySQL("sharing"),
	PipelineMySQL("sharing-2"),

	PipelinePostgreSQL("callapi"),
	PipelinePostgreSQL("chat"),
	PipelinePostgreSQL("chat-2"),
	PipelinePostgreSQL("command"),
	PipelinePostgreSQL("conversation"),
	PipelinePostgreSQL("conversation-2"),
	PipelinePostgreSQL("federation"),
	PipelinePostgreSQL("integration"),
	PipelinePostgreSQL("sharing"),
	PipelinePostgreSQL("sharing-2"),

	{
		kind: "signature",
		hmac: "8a6c9dd22806c07b68b5d263748cf226693b847a9d4773e094ba549d98834dd5"
	},
]
