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
	<section>
		<div class="section-header">
			<h4>{{ t('sharelisting', 'Shared subitems') }}</h4>
			<NcPopover popup-role="dialog">
				<template #trigger>
					<NcButton class="hint-icon"
						type="tertiary-no-background"
						:aria-label="t('sharelisting', 'Shared subitems explanation')">
						<template #icon>
							<InfoIcon :size="20" />
						</template>
					</NcButton>
				</template>
				<p class="hint-body">
					{{ t('sharelisting', 'Any other shares from subfolders') }}
				</p>
			</NcPopover>
		</div>

		<NcButton v-if="!showSubfolders" @click="loadSubfolders()">
			{{ t('sharelisting', 'Load') }}
		</NcButton>

		<div v-if="showSubfolders && shares.length === 0">
			{{ loading ? t('sharelisting', 'Loading …') : t('sharelisting', 'No shared subfolders found') }}
		</div>

		<!-- Shared subfolders list -->
		<SharedEntrySimple v-for="share in shares"
			:key="share.id"
			:title="share.name"
			:subtitle="t('sharelisting', 'Shared by {initiator}', { initiator: share.initiator })">
			<template #avatar>
				<div :class="[share.is_directory ? 'icon-folder' : 'icon-file']"
					class="avatar-subfolder" />
			</template>
			<NcActionLink icon="icon-confirm"
				:href="generateFileUrl(share.path, share.file_id)">
				{{ share.path }}
			</NcActionLink>
		</SharedEntrySimple>
	</section>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcActionLink from '@nextcloud/vue/components/NcActionLink'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import SharedEntrySimple from '../components/SharingEntrySimple.vue'
import InfoIcon from 'vue-material-design-icons/InformationOutline.vue'

export default {
	name: 'SharedSubfolders',

	components: {
		NcButton,
		NcActionLink,
		NcPopover,
		InfoIcon,
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
			loading: false,
			loaded: false,
			showSubfolders: false,
			shares: [],
		}
	},

	computed: {
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
		loadSubfolders() {
			this.showSubfolders = true
			this.fetchSharedSubfolders()
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
			return generateUrl('/apps/files?dir={dir}&fileid={fileid}', {
				dir,
				fileid,
			})
		},
	},
}
</script>

<style lang="scss" scoped>
section {
	padding-bottom: 16px;
	border-top: 2px solid var(--color-border);

	.section-header {
		margin-top: 6px;
		margin-bottom: 2px;
		display: flex;
		align-items: center;
		padding-bottom: 4px;

		h4 {
			margin: 0;
			font-size: 16px;
		}

		.visually-hidden {
			display: none;
		}

		.hint-icon {
			color: var(--color-primary-element);
		}
	}
}

.hint-body {
	max-width: 300px;
	padding: var(--border-radius-element);
}
</style>
