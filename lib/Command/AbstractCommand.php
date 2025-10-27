<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2018 Roeland Jago Douma <roeland@famdouma.nl>
// SPDX-FileCopyrightText: 2022 Solution Libre SAS
// SPDX-FileContributor: Florent Poinsaut <florent@solution-libre.fr>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\ShareListing\Command;

use OC\Core\Command\Base;
use OCA\ShareListing\Service\SharesList;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

abstract class AbstractCommand extends Base {
	public function __construct(
		protected readonly SharesList $sharesList,
	) {
		parent::__construct();
	}

	public function configure(): void {
		$this->addOption(
			'user',
			'u',
			InputOption::VALUE_OPTIONAL,
			'Will list shares of the given user'
		)->addOption(
			'path',
			'p',
			InputOption::VALUE_OPTIONAL,
			'Will only consider the given path'
		)->addOption(
			'token',
			't',
			InputOption::VALUE_OPTIONAL,
			'Will only consider the given token'
		)->addOption(
			'filter',
			'f',
			InputOption::VALUE_OPTIONAL,
			'Filter shares, possible values: owner, initiator, recipient, token, has-expiration, no-expiration'
		);
	}

	protected function getOptions(InputInterface $input): array {
		$user = $input->getOption('user');
		$path = $input->getOption('path');
		$token = $input->getOption('token');
		$filter = $this->sharesList->filterStringToInt($input->getOption('filter'));

		return [$user, $path, $token, $filter];
	}
}
