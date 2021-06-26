<!--
  - Copyright (c) 2021. The Recognize contributors.
  -
  - This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
  -->

<template>
	<div id="bookmarks">
		<figure v-if="loading" class="icon-loading loading" />
		<figure v-if="!loading && success" class="icon-checkmark success" />
		<SettingsSection
			:title="t('bookmarks', 'Status')">
      <p v-if="count >= 0">Classified images: {{count}}</p>
      <p v-else><span class="icon-loading-small"></span>&nbsp;&nbsp;&nbsp;&nbsp;Counting classified images</p>
      <p>The app is installed and will classify up to 100 images every 10 minutes.</p>
		</SettingsSection>
		<SettingsSection
			:title="t('bookmarks', 'Manual operation') ">
			<p>To trigger a full classification run manually, run the following command on the terminal.</p>
      <p>&nbsp;</p>
      <pre><code>occ recognize:classify</code></pre>
		</SettingsSection>
		<SettingsSection
			:title="t('bookmarks', 'Reset')">
			<p>Click the below button to remove all tags from all images that have been classified so far.</p>
      <button @click="onReset" class="button">Reset tags for classified images</button>
		</SettingsSection>
	</div>
</template>

<script>
import SettingsSection from '@nextcloud/vue/dist/Components/SettingsSection'
import axios from '@nextcloud/axios'

export default {
	name: 'ViewAdmin',
	components: { SettingsSection },
	data() {
		return {
			loading: false,
			success: false,
			error: '',
      count: -1
		}
	},
  async created() {
    await this.getCount()
    setInterval(() => {
      this.getCount()
    }, 60*10000)
  },

	watch: {
		error(error) {
			if (!error) return
			OC.Notification.showTemporary(error)
		},
	},
	methods: {
    async onReset() {
      this.loading = true
      await axios.get('/index.php/apps/recognize/admin/reset')
      await this.getCount()
      this.loading = false
      this.success = true
      setTimeout(() => {
        this.success = false
      }, 3000)
    },
    async getCount() {
      const resp = await axios.get('/index.php/apps/recognize/admin/count')
      const {count} = resp.data
      this.count = count
    }
  }
}
</script>
<style>
figure[class^='icon-'] {
	display: inline-block;
}

#recognize {
	position: relative;
}

#bookmarks .loading,
#bookmarks .success {
	position: absolute;
	top: 20px;
	right: 20px;
}

#bookmarks label {
	margin-top: 10px;
	display: block;
}

#bookmarks input {
	width: 50%;
	min-width: 300px;
	display: block;
}
</style>
