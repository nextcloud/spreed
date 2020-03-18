import { shallowMount } from '@vue/test-utils'
import Topbar from '../../src/components/TopBar'

describe('HelloWorld.vue', () => {
  it('renders props.msg when passed', () => {
    const msg = 'new message'
    const wrapper = shallowMount(Topbar, {
      propsData: { msg }
    })
    expect(wrapper.text()).toMatch(msg)
  })
})
