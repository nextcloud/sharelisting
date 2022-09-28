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
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Mail\IMailer;
use OCP\Util;
use Psr\Log\LoggerInterface;

class ReportSender {
	public const ACTIVITY_LIMIT = 20;

	private $mailer;
	private $userManager;
	private $defaults;
	private $l10nFactory;
	private $logger;

	/** @var SharesList */
	private $sharesList;

	public function __construct(
		IConfig $config,
		IMailer $mailer,
		IUserManager $userManager,
		Defaults $defaults,
		IFactory $l10nFactory,
		LoggerInterface $logger,
		SharesList $sharesList
	) {
		$this->config = $config;
		$this->mailer = $mailer;
		$this->userManager = $userManager;
		$this->defaults = $defaults;
		$this->l10nFactory = $l10nFactory;
		$this->logger = $logger;
		$this->sharesList = $sharesList;
	}

	public function sendReport(
		array $to,
		?string $userId = '',
		int $filter = SharesList::FILTER_NONE,
		string $path = null,
		string $token = null
	) {
		$defaultLanguage = $this->config->getSystemValue('default_language', 'en');
		$userLanguages = $this->config->getUserValue($userId, 'core', 'lang');
		$language = (!empty($userLanguages)) ? $userLanguages : $defaultLanguage;

		$l10n = $this->l10nFactory->get('shareslist', $language);

		$month = (new \DateTimeImmutable())->format('F Y');

		$template = $this->mailer->createEMailTemplate('shareslist.Notification', [
			'month' => $month,
		]);
		$template->setSubject($l10n->t('Monthly shares reports for %s', $month));
		$template->addHeader();

		$template->addBodyText('You can find the list of shares reports for the month of ' . $month . ':');
		$template->addBodyListItem('JSON');
		$template->addBodyListItem('CSV');

		$template->addFooter('', $language);

		if ($userId === null && $token === null) {
			$shares = [];
			$this->userManager->callForSeenUsers(function (IUser $user) use ($token, $path, $filter, &$shares) {
				$tmp = $this->sharesList->getFormattedShares($user->getUID(), $filter, $path, $token);
				foreach ($tmp as $share) {
					$shares[] = $share;
				}
			});
		} else {
			$shares = iter\toArray($this->sharesList->getFormattedShares($userId, $filter, $path, $token));
		}

		$json_attachment = $this->mailer->createAttachment(
			$this->sharesList->getSerializedShares($shares, 'json'),
			'report.json',
			'application/json; charset=utf-8'
		);
		$csv_attachment = $this->mailer->createAttachment(
			$this->sharesList->getSerializedShares($shares, 'csv'),
			'report.csv',
			'text/csv; charset=utf-8'
		);

		$message = $this->mailer->createMessage();
		$message->setTo($to);
		$message->useTemplate($template);
		$message->setFrom([Util::getDefaultEmailAddress('no-reply') => $this->defaults->getName()]);
		$message->attach($json_attachment);
		$message->attach($csv_attachment);

		try {
			$this->mailer->send($message);
		} catch (\Exception $e) {
			$this->logger->error($e->getMessage());
			return;
		}
	}
}
