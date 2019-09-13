<template>
	<Content :class="{'icon-loading': loading}" app-name="vueexample">
		<AppNavigation>
			<AppNavigationNew v-if="!loading" :text="t('vueexample', 'New XXXXXX')" :disabled="false"
				button-id="new-vueexample-button" button-class="icon-add" @click="newButtonAction" />
			<ul id="app-vueexample-navigation">
				<AppNavigationItem v-for="item in menu" :key="item.key" :item="item" />
			</ul>
			<AppNavigationSettings>
				Example settings
			</AppNavigationSettings>
		</AppNavigation>
		<AppContent>
			<button @click="show = !show">
				Toggle sidebar
			</button>
			<Message
				v-for="message in messages"
				:key="message.messageTime"
				v-bind="message">
				<MessageBody v-bind="message">
					<MessageBody v-if="message.parent" v-bind="messages[message.parent]" />
				</MessageBody>
			</Message>
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
			<AppSidebarTab name="Chat" icon="icon-talk">
				this is the chat tab
			</AppSidebarTab>
			<AppSidebarTab name="Activity" icon="icon-activity">
				this is the activity tab
			</AppSidebarTab>
			<AppSidebarTab name="Comments" icon="icon-comment">
				this is the comments tab
			</AppSidebarTab>
			<AppSidebarTab name="Sharing" icon="icon-shared">
				this is the sharing tab
			</AppSidebarTab>
			<AppSidebarTab name="Versions" icon="icon-history">
				this is the versions tab
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
import Message from './components/Message/Message'
import MessageBody from './components/Message/MessageBody'

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
		Message,
		MessageBody
	},
	data: function() {
		return {
			loading: false,
			date: Date.now() + 86400000 * 3,
			date2: Date.now() + 86400000 * 3 + Math.floor(Math.random() * 86400000 / 2),
			show: true,
			starred: false,
			messages: {
				1: {
					userName: 'Marco',
					messageText: 'Hello everyone',
					messageTime: '14:35',
					isFirstMessage: true

				},
				2: {
					userName: 'Joas',
					messageText: 'Please anwser to this message!!!',
					messageTime: '14:35',
					isFirstMessage: true
				},
				3: {
					userName: 'Barth',
					messageText: 'Here\'s your answer!',
					messageTime: '14:35',
					parent: 2,
					isFirstMessage: true
				},
				4: {
					userName: 'Marco',
					messageText: 'Hayy buddaaayy',
					messageTime: '14:35',
					isFirstMessage: true
				},
				5: {
					userName: 'Marco',
					messageText: 'this is a second message from marco and it\'s going to be very very very very very very very very very very very very very very very very very very very very very veryvery very very very very very very very very very very very long very very very very very very very very very very very very very very very very very very very very very veryvery very very very very very very very very very very very long :)',
					messageTime: '14:35',
					isFirstMessage: false

				},
				6: {
					userName: 'Joas',
					messageText: 'Please anwser to this message!!!',
					messageTime: '14:35',
					isFirstMessage: true
				},
				7: {
					userName: 'Barth',
					messageText: 'Here\'s your answer!',
					messageTime: '14:35',
					parent: 456,
					isFirstMessage: true
				},
				8: {
					userName: 'sertdyu',
					messageText: 'buddaaayy',
					messageTime: '14:35',
					isFirstMessage: true
				},
				9: {
					userName: 'sertdyu',
					messageText: 'buddaaayy',
					messageTime: '14:35',
					isFirstMessage: true
				},
				10: {
					userName: 'Marco',
					messageText: 'Hello everyone',
					messageTime: '14:35',
					isFirstMessage: true

				},
				11: {
					userName: 'Joas',
					messageText: 'Please anwser to this message!!!',
					messageTime: '14:35',
					isFirstMessage: true
				},
				12: {
					userName: 'Barth',
					messageText: 'Here\'s your answer!',
					messageTime: '14:35',
					parent: 456,
					isFirstMessage: true
				},
				13: {
					userName: 'sertdyu',
					messageText: 'buddaaayy',
					messageTime: '14:35',
					isFirstMessage: true
				}
			}
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
	methods: {
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
.scroller {
  height: 100%;
}
</style>
