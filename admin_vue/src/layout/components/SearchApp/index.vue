<template>
  <div :class="{'show':show}" class="header-search">
    <svg-icon class-name="search-icon" icon-class="search"/>
    <el-select
      ref="headerSearchSelect"
      v-model="value"
      filterable
      placeholder="Search"
      class="header-search-select"
      @change="change"
    >
      <el-option v-for="item in options" :key="item.value" :label="item.label" :value="item.value"/>
    </el-select>
  </div>
</template>

<script>
// fuse is a lightweight fuzzy-search module
// make search results more in line with expectations
import { setAppCode, getAppCode } from '@/utils/auth'
import { getLogoinBrowser } from '@/utils'

export default {
  name: 'HeaderSearch',
  data() {
    return {
      search: '',
      options: [],
      value: '',
      searchPool: [],
      show: true,
      fuse: undefined
    }
  },
  computed: {
  },
  watch: {
    show(value) {
      if (value) {
        document.body.addEventListener('click', this.close)
      } else {
        document.body.removeEventListener('click', this.close)
      }
    }
  },
  created() {
    const apps = this.$store.getters.userInfo.apps
    this.options = []
    apps.forEach(app => {
      this.options.push({ value: app.app_code, label: app.app_name })
    })
    this.value = getAppCode()
    setAppCode(this.value)
  },
  methods: {
    click() {
      this.show = !this.show
      if (this.show) {
        this.$refs.headerSearchSelect && this.$refs.headerSearchSelect.focus()
      }
    },
    close() {
      this.$refs.headerSearchSelect && this.$refs.headerSearchSelect.blur()
      this.options = []
      this.show = false
    },
    change(value) {
      this.value = value
      setAppCode(value)
      this.$nextTick(() => {
        if (getLogoinBrowser() === 'Safari') {
          window.location.reload()
        } else {
          this.$router.go(0)
        }
      })
    },
    // Filter out the routes that can be displayed in the sidebar
    // And generate the internationalized title
    querySearch(query) {
      if (query !== '') {
        this.options = this.fuse.search(query)
      } else {
        this.options = []
      }
    }
  }
}
</script>

<style lang="scss" scoped>
.header-search {
  font-size: 0 !important;

  .search-icon {
    cursor: pointer;
    font-size: 18px;
    vertical-align: middle;
  }

  .header-search-select {
    font-size: 18px;
    transition: width 0.2s;
    width: 0;
    overflow: hidden;
    background: transparent;
    border-radius: 0;
    display: inline-block;
    vertical-align: middle;

    ::v-deep .el-input__inner {
      border-radius: 0;
      border: 0;
      padding-left: 0;
      padding-right: 0;
      box-shadow: none !important;
      border-bottom: 1px solid #d9d9d9;
      vertical-align: middle;
    }
  }

  &.show {
    .header-search-select {
      width: 210px;
      margin-left: 10px;
    }
  }
}
</style>
