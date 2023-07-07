import Vue from 'vue'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip.js'
import App from './components/ViewUser.vue'
import AppGlobal from './mixins/AppGlobal.js'

Vue.mixin(AppGlobal)
Vue.directive('tooltip', Tooltip)

global.Recognize = new Vue({
	el: '#recognize',
	render: h => h(App),
})
