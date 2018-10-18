<?php

namespace OCA\ShareListing\Command;

use iter;
use OC\User\NoUserException;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Share;
use OCP\Share\IManager as ShareManager;
use OCP\Share\IShare;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ListShares extends Command {

	const FILTER_NONE = 0;
	const FILTER_OWNER = 1;
	const FILTER_INITIATOR = 2;
	const FILTER_RECIPIENT = 3;

	/** @var ShareManager */
	private $shareManager;

	/** @var IUserManager */
	private $userManager;

	/** @var IRootFolder */
	private $rootFolder;

	public function __construct(ShareManager $shareManager, IUserManager $userManager, IRootFolder $rootFolder) {
		parent::__construct();

		$this->shareManager = $shareManager;
		$this->userManager = $userManager;
		$this->rootFolder = $rootFolder;
	}

	public function configure() {
		$this->setName('sharing:list')
			->setDescription('List who has access to shares by owner')
			->addOption(
				'user',
				'u',
				InputOption::VALUE_OPTIONAL,
				'Will list shares of the given user'
			)
			->addOption(
				'path',
				'p',
				InputOption::VALUE_OPTIONAL,
				'Will only consider the given path'
			)->addOption(
				'filter',
				'f',
				InputOption::VALUE_OPTIONAL,
				'Filter shares, possible values: owner, initiator, recipient'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$user = $input->getOption('user');
		$path = $input->getOption('path');
		$filter = $input->getOption('filter');

		if ($filter === 'owner') {
			$filter = self::FILTER_OWNER;
		} elseif ($filter === 'initiator') {
			$filter = self::FILTER_INITIATOR;
		} else if ($filter === 'recipient') {
			$filter = self::FILTER_RECIPIENT;
		} else {
			$filter = self::FILTER_NONE;
		}

		if ($user === null) {
			$shares = [];
			$this->userManager->callForSeenUsers(function (IUser $user) use ($path, $filter, &$shares) {
				$tmp = $this->do($user->getUID(), $filter, $path);
				foreach ($tmp as $share) {
					$shares[] = $share;
				}
			});
		} else {
			$shares = iter\toArray($this->do($user, $filter, $path));
		}

		$output->writeln(json_encode($shares, JSON_PRETTY_PRINT));
	}

	private function getShareTypes(): array {
		return [
			\OCP\Share::SHARE_TYPE_USER,
			\OCP\Share::SHARE_TYPE_GROUP,
			\OCP\Share::SHARE_TYPE_LINK,
			\OCP\Share::SHARE_TYPE_EMAIL,
			\OCP\Share::SHARE_TYPE_REMOTE,
		];
	}

	private function do(string $userId, int $filter, string $path = null): \Iterator {
		$shares = $this->getShares($userId);

		// If path is set. Filter for the current user
		if ($path !== null) {
			$userFolder = $this->rootFolder->getUserFolder($userId);
			try {
				$node = $userFolder->get($path);
			} catch (NotFoundException $e) {
				// Path is not valid for user so nothing to report;
				return new \EmptyIterator();
			}
			$shares = iter\filter(function (IShare $share) use ($node) {
				if ($node->getId() === $share->getNodeId()) {
					return true;
				}
				if ($node instanceof Folder) {
					return !empty($node->getById($share->getNodeId()));
				}
				return false;
			}, $shares);
		}

		if ($filter === self::FILTER_OWNER) {
			$shares = iter\filter(function (IShare $share) use ($userId) {
				return $share->getShareOwner() === $userId;
			}, $shares);
		}
		if ($filter === self::FILTER_INITIATOR) {
			$shares = iter\filter(function (IShare $share) use ($userId) {
				return $share->getSharedBy() === $userId;
			}, $shares);
		}
		if ($filter === self::FILTER_RECIPIENT) {
			// We can't check the recipient since this might be a group share etc. However you can't share to yourself
			$shares = iter\filter(function (IShare $share) use ($userId) {
				return $share->getShareOwner() !== $userId && $share->getSharedBy() !== $userId;
			}, $shares);
		}

		$shares = iter\filter(function (IShare $share) {
			try {
				$userFolder = $this->rootFolder->getUserFolder($share->getShareOwner());
			} catch (NoUserException $e) {
				return false;
			}
			$nodes = $userFolder->getById($share->getNodeId());

			return $nodes !== [];
		}, $shares);

		$formattedShares = iter\map(function (IShare $share) {
			return $this->formatShare($share);
		}, $shares);

		return $formattedShares;
	}


	private function getShares(string $userId): \Iterator {
		$shareTypes = $this->getShareTypes();

		foreach ($shareTypes as $shareType) {
			$shares = $this->shareManager->getSharesBy($userId, $shareType, null, true, -1, 0);

			foreach ($shares as $share) {
				yield $share;
			}

			if ($shareType !== \OCP\Share::SHARE_TYPE_LINK) {
				$shares = $this->shareManager->getSharedWith($userId, $shareType, null, -1, 0);
				foreach ($shares as $share) {
					yield $share;
				}
			}
		}
	}

	private function formatShare(IShare $share): array {
		$userFolder = $this->rootFolder->getUserFolder($share->getShareOwner());
		
		$data = [
			'owner' => $share->getShareOwner(),
			'initiator' => $share->getSharedBy(),
			'time' => $share->getShareTime()->format(\DATE_ATOM),
			'permissions' => $share->getPermissions(),
		];

		$nodes = $userFolder->getById($share->getNodeId());
		$node = array_shift($nodes);
		$data['path'] = $userFolder->getRelativePath($node->getPath());

		
		if ($share->getShareType() === Share::SHARE_TYPE_USER) {
			$data['type'] = 'user';
			$data['recipient'] = $share->getSharedWith();
		}
		if ($share->getShareType() === Share::SHARE_TYPE_GROUP) {
			$data['type'] = 'group';
			$data['recipient'] = $share->getSharedWith();
		}
		if ($share->getShareType() === Share::SHARE_TYPE_LINK) {
			$data['type'] = 'link';
			$data['token'] = $share->getToken();
		}
		if ($share->getShareType() === Share::SHARE_TYPE_EMAIL) {
			$data['type'] = 'email';
			$data['recipient'] = $share->getSharedWith();
			$data['token'] = $share->getToken();
		}
		if ($share->getShareType() === Share::SHARE_TYPE_REMOTE) {
			$data['type'] = 'federated';
			$data['recipient'] = $share->getSharedWith();
		}

		return $data;
	}
}
