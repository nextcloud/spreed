<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<li class="part" :class="{ readonly: !editing }">
		<div class="part__header">
			<img class="part__icon" :src="type.iconUrl" :alt="type.name">
			<h4 class="part__heading">
				{{ type.name }}
			</h4>
			<NcActions
				class="actions"
				:container="container"
				:inline="editable ? 1 : 0"
				placement="bottom">
				<NcActionButton v-if="editable" close-after-click @click="$emit('editClicked')">
					<template #icon>
						<IconCheck v-if="editing" :size="20" />
						<IconPencilOutline v-else :size="20" />
					</template>
					{{ editing ? t('spreed', 'Save') : t('spreed', 'Edit') }}
				</NcActionButton>
				<NcActionLink :href="type.infoTarget" target="_blank" close-after-click>
					<template #icon>
						<IconInformationOutline :size="20" />
					</template>
					{{ t('spreed', 'More information') }}
				</NcActionLink>
				<NcActionButton v-if="editable" close-after-click @click="$emit('deletePart')">
					<template #icon>
						<IconTrashCanOutline :size="20" />
					</template>
					{{ t('spreed', 'Delete') }}
				</NcActionButton>
			</NcActions>
		</div>
		<div
			v-for="(field, key) in displayedFields"
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
	</li>
</template>

<script>
import { t } from '@nextcloud/l10n'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionLink from '@nextcloud/vue/components/NcActionLink'
import NcActions from '@nextcloud/vue/components/NcActions'
import IconCheck from 'vue-material-design-icons/Check.vue'
import IconInformationOutline from 'vue-material-design-icons/InformationOutline.vue'
import IconPencilOutline from 'vue-material-design-icons/PencilOutline.vue'
import IconTrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'

export default {
	name: 'BridgePart',
	components: {
		IconCheck,
		IconTrashCanOutline,
		IconInformationOutline,
		IconPencilOutline,
		NcActionButton,
		NcActionLink,
		NcActions,
	},

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
		t,
		classesOf(name) {
			const classes = {
				icon: true,
			}
			classes[this.type.fields[name].icon] = true
			return classes
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
	&__header {
		display: flex;
		align-items: center;
		gap: calc(2 * var(--default-grid-baseline));
		//width: 100%;
	}

	&__heading {
		flex-grow: 1;
		margin: 0;
	}

	&__icon {
		flex-grow: 0;
		width: var(--clickable-area-small);
		height: var(--clickable-area-small);
		filter: var(--background-invert-if-dark);
	}
}

input {
	background-size: 16px;
	background-position: 14px;
	padding-inline-start: var(--default-clickable-area);
	width: 100%;
	text-overflow: ellipsis;
	&[type=checkbox] {
		width: unset;
		margin-inline-start: 15px;
		margin-inline-end: 10px;
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

.field {
	margin: 4px 0;
}
</style>
