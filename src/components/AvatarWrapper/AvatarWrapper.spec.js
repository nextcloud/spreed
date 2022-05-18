import Vuex from 'vuex'
import { shallowMount } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import storeConfig from '../../store/storeConfig.js'
import AvatarWrapper from './AvatarWrapper.vue'

describe('AvatarWrapper.vue', () => {
	let testStoreConfig
	let store

	beforeEach(() => {
		testStoreConfig = cloneDeep(storeConfig)
		// eslint-disable-next-line import/no-named-as-default-member
		store = new Vuex.Store(testStoreConfig)
	})

	it('Renders user avatars properly', () => {
		const wrapper = shallowMount(AvatarWrapper, {
			store,
			propsData: {
				id: 'test-id',
				source: 'users',
				name: 'test-name',
			},
		})
		expect(wrapper.vm.iconClass).toBe('')
		// Check that the first child is the avatar component
		expect(wrapper.element.firstChild.nodeName).toBe('AVATAR-STUB')
		expect(wrapper.props().size).toBe(32)
	})
	it('Renders group icons properly', () => {
		const wrapper = shallowMount(AvatarWrapper, {
			store,
			propsData: {
				id: '',
				source: 'groups',
				name: '',
			},
		})
		expect(wrapper.vm.iconClass).toBe('icon-contacts')
		// Check that the first child is a div
		expect(wrapper.element.firstChild.nodeName).toBe('DIV')
	})
	it('Renders email icons properly', () => {
		const wrapper = shallowMount(AvatarWrapper, {
			store,
			propsData: {
				id: '',
				source: 'emails',
				name: '',
			},
		})
		expect(wrapper.vm.iconClass).toBe('icon-mail')
		// Check that the first child is a div
		expect(wrapper.element.firstChild.nodeName).toBe('DIV')
		// proper size
		expect(wrapper.element.firstChild.classList).toContain('avatar-32px')
	})
	it('Renders guests icons properly', () => {
		const wrapper = shallowMount(AvatarWrapper, {
			store,
			propsData: {
				id: 'random-sha1',
				source: 'guests',
				name: '',
				size: 24,
			},
		})
		expect(wrapper.element.firstChild.classList).toContain('guest')
		expect(wrapper.element.firstChild.nodeName).toBe('DIV')
		// proper size
		expect(wrapper.element.firstChild.classList).toContain('avatar-24px')
	})
})
