<template>
	<Content :class="{'icon-loading': loading}" app-name="vueexample">
		<AppNavigation>
			<AppNavigationNew
				v-if="!loading"
				:text="t('spreed', 'New conversation')"
				:disabled="false"
				button-id="new-conversation-button"
				button-class="icon-add"
				@click="newButtonAction" />
			<ul id="app-vueexample-navigation">
				<AppNavigationItem title="Billie Holiday">
					<Avatar slot="icon" user="Billie Holiday" display-name="Billie Holiday" />
					<AppNavigationCounter slot="counter" :highlighted="true">
						99+
					</AppNavigationCounter>
					<template slot="actions">
						<ActionButton icon="icon-edit" @click="alert('Edit')">
							Edit
						</ActionButton>
						<ActionButton icon="icon-delete" @click="alert('Delete')">
							Delete
						</ActionButton>
						<ActionLink icon="icon-external" title="Link" href="https://nextcloud.com" />
					</template>
				</AppNavigationItem>
				<AppNavigationItem title="Charles Mingus">
					<Avatar slot="icon" user="Charles Mingus" display-name="Charles Mingus" />
					<AppNavigationCounter slot="counter" :highlighted="true">
						3
					</AppNavigationCounter>
					<template slot="actions">
						<ActionButton icon="icon-edit" @click="alert('Edit')">
							Edit
						</ActionButton>
						<ActionButton icon="icon-delete" @click="alert('Delete')">
							Delete
						</ActionButton>
						<ActionLink icon="icon-external" title="Link" href="https://nextcloud.com" />
					</template>
				</AppNavigationItem>
				<AppNavigationItem title="The fellas" icon="icon-group">
					<AppNavigationCounter slot="counter" :highlighted="true">
						12
					</AppNavigationCounter>
					<template slot="actions">
						<ActionButton icon="icon-edit" @click="alert('Edit')">
							Edit
						</ActionButton>
						<ActionButton icon="icon-delete" @click="alert('Delete')">
							Delete
						</ActionButton>
						<ActionLink icon="icon-external" title="Link" href="https://nextcloud.com" />
					</template>
				</AppNavigationItem>
			</ul>
			<AppNavigationSettings>
				Example settings
			</AppNavigationSettings>
		</AppNavigation>
		<AppContent>
			<!--<button @click="show = !show">
				Toggle sidebar
			</button>-->
			<div :windowHeight="windowHeight" class="messages-wrapper" :style="{ height: windowHeight+'px' }">
				<MessageList />
				<NewMessageForm />
			</div>
		</AppContent>
		<AppSidebar v-show="show" title="christmas-image-2018-12-25-00:01:12.jpg" subtitle="4,3 MB, last edited 41 days ago"
			:actions="menu" :starred.sync="starred"
			@close="show=false">
			<template #action>
				<button class="primary">
					Button 1
				</button>
				<input id="link-checkbox" name="link-checkbox" class="checkbox link-checkbox"
					type="checkbox">
				<label for="link-checkbox" class="link-checkbox-label">Do something</label>
			</template>
			<AppSidebarTab name="Participants" icon="icon-talk">
				Participants
			</AppSidebarTab>
			<AppSidebarTab name="Projects" icon="icon-activity">
				Projects
			</AppSidebarTab>
		</AppSidebar>
	</Content>
</template>

<script>
import Content from 'nextcloud-vue/dist/Components/Content'
import AppContent from 'nextcloud-vue/dist/Components/AppContent'
import AppNavigation from 'nextcloud-vue/dist/Components/AppNavigation'
import AppNavigationItem from 'nextcloud-vue/dist/Components/AppNavigationItem'
import AppNavigationNew from 'nextcloud-vue/dist/Components/AppNavigationNew'
import AppNavigationSettings from 'nextcloud-vue/dist/Components/AppNavigationSettings'
import AppSidebar from 'nextcloud-vue/dist/Components/AppSidebar'
import AppSidebarTab from 'nextcloud-vue/dist/Components/AppSidebarTab'
import AppNavigationCounter from 'nextcloud-vue/dist/Components/AppNavigationCounter'
import ActionButton from 'nextcloud-vue/dist/Components/ActionButton'
import Avatar from 'nextcloud-vue/dist/Components/Avatar'
import MessageList from './components/MessageList/MessageList'
import NewMessageForm from './components/NewMessageForm/NewMessageForm'

export default {
	name: 'App',
	components: {
		Content,
		AppContent,
		AppNavigation,
		AppNavigationItem,
		AppNavigationNew,
		AppNavigationSettings,
		AppSidebar,
		AppSidebarTab,
		AppNavigationCounter,
		ActionButton,
		Avatar,
		MessageList,
		NewMessageForm
	},
	data: function() {
		return {
			loading: false,
			date: Date.now() + 86400000 * 3,
			date2: Date.now() + 86400000 * 3 + Math.floor(Math.random() * 86400000 / 2),
			show: false,
			starred: false,
			windowHeight: 0
		}
	},
	computed: {
		// App navigation
		menu: function() {
			return [
				{
					id: 'app-category-your-apps',
					classes: [],
					href: '#1',
					// action: this.log,
					icon: 'icon-category-installed',
					text: t('settings', 'Your apps')
				},
				{
					caption: true,
					text: t('vueexample', 'Section')
				},
				{
					id: 'app-category-enabled',
					classes: [],
					icon: 'icon-category-enabled',
					href: '#2',
					utils: {
						actions: [{
							icon: 'icon-delete',
							text: t('settings', 'Remove group'),
							action: function() {
								alert('remove')
							}
						}]
					},
					text: t('settings', 'Active apps')
				},
				{
					id: 'app-category-enabled',
					classes: [],
					icon: 'icon-category-enabled',
					href: '#3',
					utils: {
						counter: 123,
						actions: [
							{
								icon: 'icon-delete',
								text: t('settings', 'Remove group'),
								action: function() {
									alert('remove')
								}
							},
							{
								icon: 'icon-delete',
								text: t('settings', 'Remove group'),
								action: function() {
									alert('remove')
								}
							}
						]
					},
					text: t('settings', 'Active apps')
				},
				{
					id: 'app-category-disabled',
					classes: [],
					icon: 'icon-category-disabled',
					href: '#4',
					undo: true,
					text: t('settings', 'Disabled apps')
				}
			]
		}
	},
	beforeMount() {
		window.addEventListener('resize', this.onResize)
		this.onResize()
	},
	methods: {
		onResize() {
			this.windowHeight = window.innerHeight - document.getElementById('header').clientHeight
		},
		addOption(val) {
			this.options.push(val)
			this.select.push(val)
		},
		previous(data) {
			console.debug(data)
		},
		next(data) {
			console.debug(data)
		},
		close(data) {
			console.debug(data)
		},
		newButtonAction(e) {
			console.debug(e)
		},
		log(e) {
			console.debug(e)
		}
	}
}
</script>

<style lang="scss" scoped>
.messages-wrapper {
	display: flex;
    flex-direction: column;
}
</style>
