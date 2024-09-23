import Vue from 'vue'
import App from './components/ViewAdmin.vue'
import AppGlobal from './mixins/AppGlobal.js'

Vue.mixin(AppGlobal)

global.Recognize = new Vue({
	el: '#recognize',
	render: h => h(App),
})
