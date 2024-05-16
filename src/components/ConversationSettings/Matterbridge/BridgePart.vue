<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="part"
		:class="{ readonly: !editing }">
		<h3>
			<img class="icon-service"
				:src="type.iconUrl">
			<span>
				{{ type.name }}
			</span>
			<NcActions :container="container"
				:force-menu="false">
				<NcActionButton v-if="editable"
					:icon="editing ? 'icon-checkmark' : 'icon-rename'"
					@click="onEditClick">
					{{ editing ? t('spreed', 'Save'): t('spreed', 'Edit') }}
				</NcActionButton>
			</NcActions>
			<NcActions class="actions"
				:container="container"
				:force-menu="true"
				placement="bottom">
				<NcActionLink icon="icon-info"
					target="_blank"
					:href="type.infoTarget"
					close-after-click>
					{{ t('spreed', 'More information') }}
				</NcActionLink>
				<NcActionButton v-if="editable"
					icon="icon-delete"
					:close-after-click="true"
					@click="$emit('deletePart')">
					{{ t('spreed', 'Delete') }}
				</NcActionButton>
			</NcActions>
		</h3>
		<div v-for="(field, key) in displayedFields"
			:key="key"
			class="field">
			<!-- TODO: do not mutate prop `part` directly -->
			<!-- eslint-disable -->
			<div v-if="field.type === 'checkbox'" class="checkbox-container">
				<input
					:id="key + '-' + num"
					:ref="key"
					v-model="part[key]"
					:type="field.type"
					:class="classesOf(key)"
					:disabled="!editing">
				<label :for="key + '-' + num">
					{{ field.labelText }}
				</label>
			</div>
			<div v-else>
				<label :for="key + '-' + num" class="hidden-visually">
					{{ field.placeholder }}
				</label>
				<input
					:id="key + '-' + num"
					:ref="key"
					v-model="part[key]"
					:type="field.type"
					:class="classesOf(key)"
					:placeholder="field.placeholder"
					:readonly="readonly || !editing"
					@focus="readonly = false">
			</div>
			<!-- eslint-enable -->
		</div>
	</div>
</template>

<script>
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActionLink from '@nextcloud/vue/dist/Components/NcActionLink.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'

export default {
	name: 'BridgePart',
	components: {
		NcActions,
		NcActionButton,
		NcActionLink,
	},

	mixins: [
	],

	props: {
		num: {
			type: Number,
			required: true,
		},
		part: {
			type: Object,
			required: true,
		},
		type: {
			type: Object,
			required: true,
		},
		container: {
			type: String,
			required: true,
		},
		editing: {
			type: Boolean,
			default: false,
		},
		editable: {
			type: Boolean,
			default: true,
		},
	},

	emits: ['deletePart', 'editClicked'],

	data() {
		return {
			readonly: true,
		}
	},

	computed: {
		displayedFields() {
			if (this.editing) {
				return this.type.fields
			} else {
				const fields = {}
				if (this.type.fields[this.type.mainField]) {
					fields[this.type.mainField] = this.type.fields[this.type.mainField]
				}
				return fields
			}
		},
	},

	watch: {
		editing() {
			this.focusMainField()
		},
	},

	mounted() {
		this.focusMainField()
	},

	methods: {
		classesOf(name) {
			const classes = {
				icon: true,
			}
			classes[this.type.fields[name].icon] = true
			return classes
		},
		onEditClick() {
			this.$emit('editClicked')
		},
		// focus on main field when entering edition mode and when created
		focusMainField() {
			if (this.editing && this.$refs[this.type.mainField] && this.$refs[this.type.mainField].length > 0) {
				this.$refs[this.type.mainField][0].focus()
				this.$refs[this.type.mainField][0].select()
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.part {
	padding-top: 10px;
}

h3 {
	display: flex;
	margin-bottom: 0;

	> span {
		flex-grow: 1;
		padding-top: 12px;
	}

	.icon-service {
		flex-grow: 0;
		padding: 0 !important;
		margin: 14px 10px 0 14px;
		width: 16px;
		height: 16px;
		filter: var(--background-invert-if-dark);
	}
}

input {
	background-size: 16px;
	background-position: 14px;
	padding-left: 44px;
	width: 100%;
	text-overflow: ellipsis;
	&[type=checkbox] {
		width: unset;
		margin-left: 15px;
		margin-right: 10px;
	}
}

.readonly input {
	border: 0;
}

.checkbox-container {
	display: flex;
	height: 40px;

	> label {
		flex-grow: 1;
		line-height: 40px;
	}

	&:hover {
		opacity: 1;
		background-color: var(--color-background-hover);
		border-radius: var(--border-radius-large);
	}
}

// Force action buttons to be 44px tall;
:deep(.action-item__menutoggle) {
	height: var(--default-clickable-area) !important;
}

.field {
	margin: 4px 0;
}
</style>
