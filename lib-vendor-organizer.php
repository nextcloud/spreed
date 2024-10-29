#!/usr/bin/env php
<?php
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$sourceDirectory = $argv[1];
$sourceDirectory = rtrim($sourceDirectory, '/') . '/';

if (!str_starts_with('/', $sourceDirectory)) {
	$sourceDirectory = getcwd() . '/' . $sourceDirectory;
}

$stripNamespacePrefix = $argv[2] ?? '';
if ($stripNamespacePrefix) {
	printf("Namespace Prefix to strip from destination dir is %s%s", $stripNamespacePrefix, PHP_EOL);
}

if (!file_exists($sourceDirectory) || !is_dir($sourceDirectory)) {
	print("Directory not found");
	exit(1);
}
$organizationList = [];
foreach(scandir($sourceDirectory) as $file) {
	if (!is_dir($sourceDirectory . $file) || $file === '.' || $file === '..') {
		continue;
	}
	$organizationList[] = $sourceDirectory . $file . '/';
}

$projectList = [];
foreach($organizationList as $organizationDir) {
	foreach(scandir($organizationDir) as $file) {
		if (!is_dir($organizationDir . $file) || $file === '.' || $file === '..') {
			continue;
		}
		$projectList[] = $organizationDir . $file . '/';
	}
}

foreach ($projectList as $projectDir) {
	if (!file_exists($projectDir . 'composer.json')) {
		continue;
	}
	$projectInfo = json_decode(file_get_contents($projectDir . 'composer.json'), true);
	if (!isset($projectInfo['autoload']['psr-4'])) {
		printf("No supported autoload configuration in %s" . PHP_EOL, $projectDir);
		exit(2);
	}
	foreach ($projectInfo['autoload']['psr-4'] as $namespace => $codeDir) {
		if ($stripNamespacePrefix !== '' && strpos($namespace, $stripNamespacePrefix) === 0) {
			$namespace = str_replace($stripNamespacePrefix, '', $namespace);
		}
		$destination = $sourceDirectory . str_replace('\\', '/', $namespace);
		if (file_exists($destination)) {
			rmdir_recursive($destination);
		}
		mkdir($destination, 0777, true);
		if (!rename($projectDir . $codeDir, $destination)) {
			printf("Failed to move %s to %s" . PHP_EOL, $projectDir . $codeDir, $destination);
			exit(3);
		}
	}
}

foreach($organizationList as $organizationDir) {
	rmdir_recursive($organizationDir);
}

function rmdir_recursive($dir) {
	foreach(scandir($dir) as $file) {
		if ('.' === $file || '..' === $file) {
			continue;
		}
		if (is_dir("$dir/$file")) {
			rmdir_recursive("$dir/$file");
		} else {
			unlink("$dir/$file");
		}
	}
	rmdir($dir);
}
