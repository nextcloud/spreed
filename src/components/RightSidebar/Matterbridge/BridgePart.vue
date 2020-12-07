<!--
  - @copyright Copyright (c) 2020 Julien Veyssier <eneiluj@posteo.net>
  -
  - @author Julien Veyssier <eneiluj@posteo.net>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
-->

<template>
	<div :class="{ part: true, readonly: !editing }">
		<h3>
			<img class="icon-service"
				:src="type.iconUrl" />
			<span>
				{{ type.name }}
			</span>
			<Actions
				:force-menu="false">
				<ActionButton
					:icon="editing ? 'icon-checkmark' : 'icon-rename'"
					@click="onEditClick">
					{{ editing ? t('spreed', 'Ok'): t('spreed', 'Edit') }}
				</ActionButton>
			</Actions>
			<Actions
				:force-menu="true"
				placement="bottom">
				<ActionLink
					icon="icon-info"
					title="More information"
					target="_blank"
					:href="type.infoTarget"
					:close-after-click="true" />
				<ActionButton
					icon="icon-delete"
					:close-after-click="true"
					@click="$emit('delete-part')">
					{{ t('spreed', 'Delete') }}
				</ActionButton>
			</Actions>
		</h3>
		<div v-for="(field, key) in displayedFields" :key="key">
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
		</div>
	</div>
</template>

<script>
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionLink from '@nextcloud/vue/dist/Components/ActionLink'

export default {
	name: 'BridgePart',
	components: {
		Actions,
		ActionButton,
		ActionLink,
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
		deletable: {
			type: Boolean,
			default: true,
		},
		editing: {
			type: Boolean,
			default: false,
		},
	},

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

	mounted() {
		this.focusMainField()
	},

	watch: {
		editing() {
			this.focusMainField()
		},
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
			this.$emit('edit-clicked')
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

button {
	display: inline-block;
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
	}
}

body.theme--dark .icon-service {
	-webkit-filter: invert(1);
	filter: invert(1);
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
</style>
