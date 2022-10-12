<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022 Solution Libre SAS
 * @copyright Copyright (c) 2020 Robin Appelman <robin@icewind.nl>
 *
 * @author Florent Poinsaut <florent@solution-libre.fr>
 * @author Robin Appelman <robin@icewind.nl>
 * 
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\ShareListing\Service;

use iter;
use OCA\ShareListing\Service\SharesList;
use OCP\Defaults;
use OCP\Files\FileInfo;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Mail\IMailer;
use OCP\Util;
use Psr\Log\LoggerInterface;

class ReportSender {
	public const ACTIVITY_LIMIT = 20;

	/** @var string */
	private $appName;
	/** @var Iconfig */
	private $config;

	private $mailer;
	private $userManager;
	private $defaults;
	private $l10nFactory;
	private $logger;

	/** @var SharesList */
	private $sharesList;
	/** @var IRootFolder */
	private $root;
	/** @var IURLGenerator */
	protected $url;

	public function __construct(
		string $appName,
		IConfig $config,
		IMailer $mailer,
		IUserManager $userManager,
		Defaults $defaults,
		IFactory $l10nFactory,
		LoggerInterface $logger,
		SharesList $sharesList,
		IRootFolder $root,
		IURLGenerator $url
	) {
		$this->appName = $appName;
		$this->config = $config;
		$this->mailer = $mailer;
		$this->userManager = $userManager;
		$this->defaults = $defaults;
		$this->l10nFactory = $l10nFactory;
		$this->logger = $logger;
		$this->sharesList = $sharesList;
		$this->root = $root;
		$this->url = $url;
	}

	public function createReport(
		string $recipient,
		string $targetPath,
		\DateTimeImmutable $dateTime,
		?string $userId = '',
		int $filter = SharesList::FILTER_NONE,
		string $path = null,
		string $token = null
	) {
        $userFolder = $this->root->getUserFolder($recipient);

		if ($userFolder->nodeExists($targetPath)) {
			/** @var Folder $folder */
			$folder = $userFolder->get($targetPath);
			if ($folder->getType() !== FileInfo::TYPE_FOLDER) {
				return ['error' => 'Target path ' . $targetPath . ' is not a folder'];
			}
		} else {
			$folder = $userFolder->newFolder($targetPath);
		}

		$shares = iter\toArray($this->sharesList->getFormattedShares($userId, $filter, $path, $token));

		$formatedDateTime = $dateTime->format('YmdHi');
		$reports = [];
		foreach (['json', 'csv'] as $format) {
			$fileName=$formatedDateTime.' - Shares report.'.$format;
			$savedFile = $folder->newFile($fileName);
			$savedFile->putContent($this->sharesList->getSerializedShares($shares, $format));
			$reports[] = [
				'name' => $savedFile->getName(),
				'url' => $this->url->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $savedFile->getId()])
			];
		}

		return $reports;
	}

	public function sendReport(string $recipient, \DateTimeImmutable $dateTime, array $reports) {
		$defaultLanguage = $this->config->getSystemValue('default_language', 'en');
		$userLanguages = $this->config->getUserValue($recipient, 'core', 'lang');
		$language = (!empty($userLanguages)) ? $userLanguages : $defaultLanguage;

		$l10n = $this->l10nFactory->get('shareslist', $language);

		$template = $this->mailer->createEMailTemplate('shareslist.Notification', [
			'date-time' => $dateTime,
		]);

		$formatedDateTime = $dateTime->format(\DateTimeInterface::COOKIE);
		$template->setSubject($l10n->t('Shares reports generated on %s', $formatedDateTime));
		$template->addHeader();
		$template->addBodyText('You can find the list of shares reports generated on ' . $formatedDateTime . ':');

		foreach ($reports as $report) {
			$template->addBodyListItem(
				'<a href="'.$report['url'].'">'.$report['name'].'</a>',
				'',
				'',
				$report['name'].': '.$report['url']);
		}

		$template->addFooter('', $language);

		$message = $this->mailer->createMessage();
		$message->setTo([$this->getEmailAdressFromUserId($recipient)]);
		$message->useTemplate($template);
		$message->setFrom([Util::getDefaultEmailAddress('no-reply') => $this->defaults->getName()]);

		try {
			$this->mailer->send($message);
		} catch (\Exception $e) {
			$this->logger->error($e->getMessage());
			return;
		}
	}

	protected function getEmailAdressFromUserId(string $userId): ?string {
		$user = $this->userManager->get($userId);
		if ($user === null) {
			$this->logger->warning(
				'ShareList error, the user "' . $userId . '" does not exist.',
				['app' => $this->appName]
			);
			return null;
		}

		$email = $user->getEMailAddress();
		if ($email === null || $email === '') {
			$this->logger->warning(
				'ShareList error, the user "' . $userId . '" does not have an email set up.',
				['app' => $this->appName]
			);
			return null;
		}

		return $email;
	}
}
