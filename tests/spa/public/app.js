Vue.createApp({
  template: `<nav>
    <router-link to="/">Home</router-link>
    <router-link to="/about">About</router-link>
    <router-link to="/asdf">Asdf</router-link>
  </nav>
  <router-view/>`,
}).use(VueRouter.createRouter({
  routes: [
    {
      path: '/', component: {
        template: `<h1>Home</h1>`
      }
    },
    {
      path: '/about', component: {
        template: `<h1>About</h1>
        {{ data }}`,
        setup() {
          const data = Vue.ref({})
          Vue.onMounted(() => {
            fetch('/api')
              .then(r => r.json())
              .then(d => data.value = d)
          })
          return { data }
        }
      }
    },
    {
      path: '/:pathMatch(.*)', component: {
        template: `<h1>Not Found</h1>`
      }
    }
  ],
  history: VueRouter.createWebHistory()
})).mount('#app')