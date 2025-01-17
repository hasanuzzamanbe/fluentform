import Vue from 'vue';
import AddOnModules from './views/AddonModules';
import notifier from './notifier';

import {
    Button,
    Select,
    Input,
    Switch,
    Notification,
    RadioButton,
    Radio,
    RadioGroup,
    Row,
    Col
} from 'element-ui';

Vue.use(RadioButton);
Vue.use(Radio);
Vue.use(RadioGroup);
Vue.use(Button);
Vue.use(Select);
Vue.use(Input);
Vue.use(Switch);
Vue.use(Row);
Vue.use(Col);

Vue.prototype.$notify = Notification;

Vue.mixin({
    methods: {
        $t(str) {
            let transString = window.fluent_addon_modules.addOnModule_str[str];
            if (transString) {
                return transString;
            }
            return str;
        },
        ...notifier
    }
});

var app = new Vue({
    el: '#ff_add_ons_app',
    components: {
        'fluent-add-ons': AddOnModules
    }
});
