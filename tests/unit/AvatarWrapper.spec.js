import { shallowMount } from '@vue/test-utils'
import AvatarWrapper from '../../src/components/AvatarWrapper'
import { translate } from '@nextcloud/l10n'

describe('AvatarWrapper.vue', () => {
	it('Renders user avatars properly', () => {
		const wrapper = shallowMount(AvatarWrapper, {
			propsData: { 
				id: 'mario',
				ource: 'users',
				name: 'mario',}
		})
		expect(wrapper.vm.iconClass).toBe('')
		//Check that the first child is the avatar component
		expect(wrapper.element.firstChild.nodeName).toBe('AVATAR-STUB')
	})
	it('Renders group icons properly', () => {
		const wrapper = shallowMount(AvatarWrapper, {
			propsData: { 
				id: '',
				source: 'groups',
				name: '',
			}
		})
		expect(wrapper.vm.iconClass).toBe('icon-contacts')
		//Check that the first child is a div
		expect(wrapper.element.firstChild.nodeName).toBe('DIV')
	})
	it('Renders email icons properly', () => {
		const wrapper = shallowMount(AvatarWrapper, {
			propsData: { 
				id: '',
				source: 'emails',
				name: ''
			}
		})
		expect(wrapper.vm.iconClass).toBe('icon-mail')
		//Check that the first child is a div
		expect(wrapper.element.firstChild.nodeName).toBe('DIV')
	})
	it('Renders guests icons properly', () => {

		const wrapper = shallowMount(AvatarWrapper, {
			propsData: { 
				id: '',
				name: '',
			},
			mocks: {
				't' : (string) => string
			}
		})
		expect(wrapper.element).toBe('m')
		//Check that the first child is a div
		// expect(wrapper.element.firstChild.nodeName).toBe('DIV')
	})

})
