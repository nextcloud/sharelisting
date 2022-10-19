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

use Icewind\SMB\Exception\NotFoundException;
use iter;
use OC\Files\Search\SearchBinaryOperator;
use OC\Files\Search\SearchComparison;
use OC\Files\Search\SearchOrder;
use OC\Files\Search\SearchQuery;
use OCA\ShareListing\Service\SharesList;
use OCP\Defaults;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\InvalidDirectoryException;
use OCP\Files\IRootFolder;
use OCP\Files\Search\ISearchBinaryOperator;
use OCP\Files\Search\ISearchComparison;
use OCP\Files\Search\ISearchOrder;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Mail\IMailer;
use OCP\Util;
use Psr\Log\LoggerInterface;
use Swaggest\JsonDiff\JsonDiff;

class ReportSender
{
	protected const REPORT_NAME = ' - Shares report.';

	/** @var string */
	private $appName;
	/** @var Iconfig */
	private $config;
	/** @var ?array */
	protected $diffReport = null;

	private $mailer;
	private $userManager;
	private $defaults;
	private $l10nFactory;
	private $logger;

	/** @var array */
	protected $reports = [];
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
				$this->logger->warning(
					'Target path ' . $targetPath . ' is not a folder',
					['app' => $this->appName]
				);
			}
		} else {
			$folder = $userFolder->newFolder($targetPath);
		}

		$formats = ['json', 'csv'];
		$formatedDateTime = $dateTime->format('YmdHi');
		foreach ($formats as $key => $format) {
			$fileName = $formatedDateTime . self::REPORT_NAME . $format;
			if (!array_key_exists($fileName, $this->reports)) {
				if ($key === array_key_first($formats)) {
					$shares = iter\toArray($this->sharesList->getFormattedShares($userId, $filter, $path, $token));
				}
				$reportFile = $folder->newFile($fileName);
				$data = $this->sharesList->getSerializedShares($shares, $format);
				$reportFile->putContent($data);
				$this->reports[$reportFile->getName()] = [
					'url' => $this->url->linkToRouteAbsolute(
						'files.View.showFile',
						['fileid' => $reportFile->getId()]
					),
					'data' => $data
				];
			}
		}
	}

	public function sendReport(string $recipient, \DateTimeImmutable $dateTime)
	{
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

		foreach ($this->reports as $name => $value) {
			$template->addBodyListItem(
				'<a href="' . $value['url'] . '">' . $name . '</a>',
				'',
				'',
				$name . ': ' . $value['url']
			);
		}
		if ($this->diffReport !== null) {
			$template->addBodyText('You can also find the differential between this report and the previous one:');
			$template->addBodyListItem(
				'<a href="' . $this->diffReport['url'] . '">' . $this->diffReport['fileName'] . '</a>',
				'',
				'',
				$this->diffReport['fileName'] . ': ' . $this->diffReport['url']
			);
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

	protected function getEmailAdressFromUserId(string $userId): ?string
	{
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

	public function diff(
		string $userId,
		string $dir
	) {
		$userFolder = $this->root->getUserFolder($userId);

		if ($userFolder->nodeExists($dir)) {
			/** @var Folder $folder */
			$folder = $userFolder->get($dir);
			if ($folder->getType() !== FileInfo::TYPE_FOLDER) {
				throw new InvalidDirectoryException('Invalid directory, "' . $dir . '" not a folder');
			}
		} else {
			throw new InvalidDirectoryException('Invalid directory, "' . $dir . '" does not exist');
		}

		$search = $userFolder->search(
			new SearchQuery(
				new SearchBinaryOperator(
					ISearchBinaryOperator::OPERATOR_OR,
					[
						new SearchComparison(
							ISearchComparison::COMPARE_LIKE,
							'name',
							'%' . self::REPORT_NAME . 'json'
						)
					]
				),
				1,
				1,
				[new SearchOrder(ISearchOrder::DIRECTION_DESCENDING, 'mtime')]
			)
		);

		if (empty($search)) {
			throw new NotFoundException('No previous report found on this folder.');
		}

		$previousFile = $search[0];
		$previousFilename = $previousFile->getName();
		$previousDateTime = substr($previousFilename, 0, 12);
		$previousContent = json_decode($previousFile->getContent());
		$previousContentWithId = [];
		foreach ($previousContent as $value) {
			$previousContentWithId[$value->id] = $value;
		}

		$newFilename = array_keys($this->reports)[0];
		$newDateTime = substr($newFilename, 0, 12);
		$newContent = json_decode(array_values($this->reports)[0]['data']);
		$newContentWithId = [];
		foreach ($newContent as $value) {
			$newContentWithId[$value->id] = $value;
		}

		$jsonDiff = new JsonDiff(
			$previousContentWithId,
			$newContentWithId,
			JsonDiff::REARRANGE_ARRAYS + JsonDiff::COLLECT_MODIFIED_DIFF
		);

		$reportFilename = $previousDateTime . ' - ' . $newDateTime . ' - Shares report diff.json';
		$reportFile = $folder->newFile($reportFilename);
		$res = [
			'added' => $jsonDiff->getAdded(),
			'removed' => $jsonDiff->getRemoved(),
			'modified' => $jsonDiff->getModifiedDiff()
		];

		$reportFile->putContent(json_encode($res, JSON_PRETTY_PRINT));

		$this->diffReport = [
			'url' => $this->url->linkToRouteAbsolute(
				'files.View.showFile',
				['fileid' => $reportFile->getId()]
			),
			'fileName' => $reportFilename
		];
	}
}
