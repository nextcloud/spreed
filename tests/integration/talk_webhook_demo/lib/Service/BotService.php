<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TalkWebhookDemo\Service;

use OCA\Talk\Events\BotInstallEvent;
use OCA\Talk\Events\BotUninstallEvent;
use OCA\TalkWebhookDemo\Model\Bot;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Security\ISecureRandom;

class BotService {
	public function __construct(
		protected IConfig $config,
		protected IURLGenerator $url,
		protected IEventDispatcher $dispatcher,
		protected IFactory $l10nFactory,
		protected ISecureRandom $random,
	) {
	}

	public function installBot(string $backend): void {
		$id = sha1($backend);

		$secretData = $this->config->getAppValue('talk_webhook_demo', 'secret_' . $id);
		if ($secretData) {
			$secretArray = json_decode($secretData, true, 512, JSON_THROW_ON_ERROR);
			$secret = $secretArray['secret'] ?? $this->random->generate(64, ISecureRandom::CHAR_HUMAN_READABLE);
		} else {
			$secret = $this->random->generate(64, ISecureRandom::CHAR_HUMAN_READABLE);
		}
		foreach (Bot::SUPPORTED_LANGUAGES as $lang) {
			$this->installLanguage($secret, $lang);
		}

		$this->config->setAppValue('talk_webhook_demo', 'secret_' . $id, json_encode([
			'id' => $id,
			'secret' => $secret,
			'backend' => $backend,
		], JSON_THROW_ON_ERROR));
	}

	protected function installLanguage(string $secret, string $lang): void {
		$libL10n = $this->l10nFactory->get('lib', $lang);
		$langName = $libL10n->t('__language_name__');
		if ($langName === '__language_name__') {
			$langName = $lang === 'en' ? 'British English' : $lang;
		}

		$l = $this->l10nFactory->get('talk_webhook_demo', $lang);

		$event = new BotInstallEvent(
			$l->t('Webhook Demo'),
			$secret . str_replace('_', '', $lang),
			$this->url->linkToOCSRouteAbsolute('talk_webhook_demo.Bot.receiveWebhook', ['lang' => $lang]),
			$l->t('Call summary (%s)', $langName) . ' - ' . $l->t('The call summary bot posts an overview message after the call listing all participants and outlining tasks'),
		);
		try {
			$this->dispatcher->dispatchTyped($event);
		} catch (\Throwable) {
		}
	}

	public function uninstallBot(string $secret, string $backend): void {
		foreach (Bot::SUPPORTED_LANGUAGES as $lang) {
			$this->uninstallLanguage($secret, $backend, $lang);
		}
	}

	protected function uninstallLanguage(string $secret, string $backend, string $lang): void {
		$absoluteUrl = $this->url->getAbsoluteURL('');
		$backendUrl = rtrim($backend, '/') . '/' . substr($this->url->linkToOCSRouteAbsolute('talk_webhook_demo.Bot.receiveWebhook', ['lang' => $lang]), strlen($absoluteUrl));

		$event = new BotUninstallEvent(
			$secret . str_replace('_', '', $lang),
			$backendUrl,
		);
		try {
			$this->dispatcher->dispatchTyped($event);
		} catch (\Throwable $e) {
		}

		// Also remove legacy secret bots
		$event = new BotUninstallEvent(
			$secret,
			$backendUrl,
		);
		try {
			$this->dispatcher->dispatchTyped($event);
		} catch (\Throwable) {
		}
	}
}
