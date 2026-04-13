import { createApp } from 'vue'
import App from './components/ViewAdmin.vue'
import AppGlobal from './mixins/AppGlobal.js'

const app = createApp(App)
app.mixin(AppGlobal)

global.Recognize = app.mount('#recognize')
