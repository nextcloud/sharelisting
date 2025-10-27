<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2020 Robin Appelman <robin@icewind.nl>
// SPDX-FileCopyrightText: 2022 Solution Libre SAS
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\ShareListing\Service;

use iter;
use OC\Files\Search\SearchBinaryOperator;
use OC\Files\Search\SearchComparison;
use OC\Files\Search\SearchOrder;
use OC\Files\Search\SearchQuery;
use OCP\Defaults;
use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\Files\Folder;
use OCP\Files\InvalidDirectoryException;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
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

class ReportSender {
	protected const REPORT_NAME = ' - Shares report.';

	protected ?array $diffReport = null;
	protected array $reports = [];

	public function __construct(
		private readonly string $appName,
		private readonly IConfig $config,
		private readonly IMailer $mailer,
		private readonly IUserManager $userManager,
		private readonly Defaults $defaults,
		private readonly IFactory $l10nFactory,
		private readonly LoggerInterface $logger,
		private readonly SharesList $sharesList,
		private readonly IRootFolder $root,
		private readonly IURLGenerator $url,
	) {
	}

	public function createReport(
		string $recipient,
		string $targetPath,
		\DateTimeImmutable $dateTime,
		?string $userId = '',
		int $filter = SharesList::FILTER_NONE,
		?string $path = null,
		?string $token = null,
	): void {
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

	public function sendReport(string $recipient, \DateTimeImmutable $dateTime): void {
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

		$template->addBodyText('Permissions mapping:');
		$template->addBodyListItem('1 = read');
		$template->addBodyListItem('2 = update');
		$template->addBodyListItem('4 = create');
		$template->addBodyListItem('8 = delete');
		$template->addBodyListItem('16 = share');
		$template->addBodyListItem('31 = all (default: 31, for public shares: 1)');

		$template->addFooter('', $language);

		$message = $this->mailer->createMessage();
		$message->setTo([$this->getEmailAdressFromUserId($recipient)]);
		$message->useTemplate($template);
		$message->setFrom([Util::getDefaultEmailAddress('no-reply') => $this->defaults->getName()]);

		try {
			$this->mailer->send($message);
		} catch (\Exception $e) {
			$this->logger->error($e->getMessage());
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

	public function diff(
		string $userId,
		string $dir,
	): void {
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

		/** @var File $previousFile */
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
