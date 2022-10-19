<!--
  - @copyright Copyright (c) 2019 John Molakvoæ <skjnldsv@protonmail.com>
  -
  - @author John Molakvoæ <skjnldsv@protonmail.com>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div class="sharing-entry__subfolders">
		<!-- Main collapsible entry -->
		<SharedEntrySimple :title="mainTitle" :subtitle="subTitle">
			<template #avatar>
				<div class="avatar-subfolder avatar-subfolder--primary">
					<svg xmlns="http://www.w3.org/2000/svg" height="22" viewBox="0 0 16 16" width="22" version="1.1"><path fill="white" d="m1.5 2c-0.25 0-0.5 0.25-0.5 0.5v11c0 0.26 0.24 0.5 0.5 0.5h13c0.26 0 0.5-0.241 0.5-0.5v-9c0-0.25-0.25-0.5-0.5-0.5h-6.5l-2-2h-4.5zm8.75 3.5a1.25 1.25 0 0 1 1.25 1.25 1.25 1.25 0 0 1 -1.25 1.25 1.25 1.25 0 0 1 -0.8008 -0.291l-2.4512 1.2265a1.25 1.25 0 0 1 0.002 0.0645 1.25 1.25 0 0 1 -0.0039 0.0645l2.4531 1.2265a1.25 1.25 0 0 1 0.8008 -0.291 1.25 1.25 0 0 1 1.25 1.25 1.25 1.25 0 0 1 -1.25 1.25 1.25 1.25 0 0 1 -1.25 -1.25 1.25 1.25 0 0 1 0.0039 -0.064l-2.4531-1.227a1.25 1.25 0 0 1 -0.8008 0.291 1.25 1.25 0 0 1 -1.25 -1.25 1.25 1.25 0 0 1 1.25 -1.25 1.25 1.25 0 0 1 0.8008 0.291l2.4512-1.2265a1.25 1.25 0 0 1 -0.002 -0.0645 1.25 1.25 0 0 1 1.25 -1.25z"/></svg>
				</div>
			</template>
			<NcActionButton :icon="showSubfoldersIcon" @click.prevent.stop="toggleSubfolders">
				{{ t('sharelisting', 'Toggle subfolders listing') }}
			</NcActionButton>
		</SharedEntrySimple>

		<!-- Shared subfolders list -->
		<SharedEntrySimple v-for="share in shares"
			:key="share.id"
			class="sharing-entry__subfolder"
			:title="share.name"
			:subtitle="t('sharelisting', 'Shared by {initiator}', { initiator: share.initiator })">
			<template #avatar>
				<div :class="[share.is_directory ? 'icon-folder' : 'icon-file']" class="avatar-subfolder" />
			</template>
			<NcActionLink icon="icon-confirm" :href="generateFileUrl(share.path, share.file_id)">
				{{ share.path }}
			</NcActionLink>
		</SharedEntrySimple>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActionLink from '@nextcloud/vue/dist/Components/NcActionLink.js'
import SharedEntrySimple from '../components/SharingEntrySimple.vue'

export default {
	name: 'SharedSubfolders',

	components: {
		NcActionButton,
		NcActionLink,
		SharedEntrySimple,
	},

	props: {
		fileInfo: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			loaded: false,
			loading: false,
			showSubfolders: false,
			shares: [],
		}
	},

	computed: {
		showSubfoldersIcon() {
			if (this.loading) {
				return 'icon-loading-small'
			}
			if (this.showSubfolders) {
				return 'icon-triangle-n'
			}
			return 'icon-triangle-s'
		},
		mainTitle() {
			return t('sharelisting', 'Shared subitems')
		},
		subTitle() {
			return (this.showSubfolders && this.shares.length === 0)
				? t('sharelisting', 'No shared subfolders found')
				: ''
		},
		fullPath() {
			const path = `${this.fileInfo.path}/${this.fileInfo.name}`
			return path.replace('//', '/')
		},
	},

	watch: {
		fileInfo() {
			this.resetState()
		},
	},

	methods: {
		/**
		 * Toggle the list view and fetch/reset the state
		 */
		toggleSubfolders() {
			this.showSubfolders = !this.showSubfolders
			if (this.showSubfolders) {
				this.fetchSharedSubfolders()
			} else {
				this.resetState()
			}
		},

		/**
		 * Fetch the shared subfolders array
		 */
		async fetchSharedSubfolders() {
			this.loading = true
			try {
				const url = generateOcsUrl(`apps/sharelisting/api/v1/sharedSubfolders?format=json&path=${this.fullPath}`, 2)
				const shares = await axios.get(url.replace(/\/$/, ''))
				this.shares = shares.data.ocs.data
				this.loaded = true
			} catch (error) {
				OC.Notification.showTemporary(t('sharelisting', 'Unable to fetch the shared subfolders'), { type: 'error' })
			} finally {
				this.loading = false
			}
		},

		/**
		 * Reset current component state
		 */
		resetState() {
			this.loaded = false
			this.loading = false
			this.showSubfolders = false
			this.shares = []
		},

		/**
		 * Generate a file app url to a provided path
		 *
		 * @param {string} dir the absolute url to the folder
		 * @param {number} fileid the node id
		 * @return {string}
		 */
		generateFileUrl(dir, fileid) {
			return generateUrl('/apps/files?dir={dir}&fileid={fileid}', { dir, fileid })
		},
	},
}
</script>

<style lang="scss" scoped>
.sharing-entry__subfolders {
	.avatar-subfolder {
		width: 32px;
		height: 32px;
		line-height: 32px;
		font-size: 18px;
		border-radius: 50%;
		flex-shrink: 0;
		padding: 4px;
		&--primary {
			background-color: var(--color-primary-element);
		}
	}

	.sharing-entry__subfolder {
		padding-left: 36px;
	}
}
</style>
