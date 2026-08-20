import { createApp } from "vue";
import "./style.css";
import App from "./App.vue";
import router from "./router/index.js";
import "vue3-toastify/dist/index.css";
import { configurePhilippineDateTimeDefaults } from "./utils/philippineDateTime";

configurePhilippineDateTimeDefaults();
const app = createApp(App);

app.use(router);

app.mount("#app");
