import Vue from 'vue'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip'
import App from './components/ViewUser'
import AppGlobal from './mixins/AppGlobal'

Vue.mixin(AppGlobal)
Vue.directive('tooltip', Tooltip)

global.Recognize = new Vue({
	el: '#recognize',
	render: h => h(App),
})
