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
	<div class="part">
		<h3>
			<span>
				{{ type.name }}
			</span>
			<a v-if="deletable"
				class="icon icon-delete"
				@click="$emit('deletePart')" />
		</h3>
		<div v-for="(field, key) in type.fields" :key="key">
			<label :for="key + '-' + num">
				<a :class="classesOf(key)" />
				<span
					class="hidden-visually">
					{{ field.placeholder }}
				</span>
			</label>
			<input v-model="part[key]"
				:type="field.type"
				:id="key + '-' + num"
				:placeholder="field.placeholder"
				:readonly="readonly"
				@focus="readonly = false">
		</div>
	</div>
</template>

<script>

export default {
	name: 'BridgePart',
	components: {
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
	},

	data() {
		return {
			readonly: true,
		}
	},

	computed: {
	},

	beforeMount() {
	},

	beforeDestroy() {
	},

	methods: {
		classesOf(name) {
			const classes = {
				icon: true,
			}
			classes[this.type.fields[name].icon] = true
			return classes
		},
	},
}
</script>

<style scoped>
.part {
	padding-top: 10px;
}

button {
	display: inline-block;
}

h3 {
	padding-left: 35px;
	text-align: left;
	display: grid;
	grid-template: 1fr / 90% 10%;
}

input {
	display: inline-block;
	width: 88%;
}

.icon {
	display: inline-block;
	width: 8%;
}
</style>
