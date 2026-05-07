<template>
  <div class="app-container">
    <span class="el-col-span">投放方式：</span>
    <el-select v-model="listQuery.merchantId" placeholder="商户号" clearable class="filter-item" style="width: 200px">
      <el-option v-for="item in merchants" :key="item.id" :label="item.name" :value="item.id" />
    </el-select>
    <el-button class="filter-item" type="primary" icon="el-icon-search" @click="getQrcode">
      生成二维码
    </el-button>
    <div class="demo-image upload-container" v-if="imageShow">
      <el-image
        style="width: 250px; height: 250px"
        :src="url"
        fit="fill"
      >
      </el-image>
    </div>
  </div>
</template>

<script>
import { qrcode } from '@/api/profit/withdrawal'
import { Message } from 'element-ui'

export default {
  name: 'WechatCode',
  data() {
    return {
      url: '',
      listQuery: {
        merchantId: undefined
      },
      imageShow: false,
      merchants: [
        { name: '成都星耀-1623882753', id: '1623882753', appId: 'wx8c398b032e619d49' },
        { name: '北海星-1626335475', id: '1626335475', appId: 'wxf9f61fdf56077a5b' },
        { name: '北海星-1628173139', id: '1628173139', appId: 'wxf9f61fdf56077a5b' }
      ]
    }
  },
  created() {},
  methods: {
    getQrcode() {
      var merchant = this.merchants.filter(m => this.listQuery.merchantId === m.id).shift()
      if (merchant === undefined) {
        Message({
          showClose: true,
          message: 'error',
          type: 'error',
          duration: 5 * 1000
        })
        return
      }
      qrcode(merchant.id, merchant.appId).then(response => {
        this.url = response.data.image
        this.imageShow = true
      })
    }
  }
}
</script>

<style lang="scss" scoped>
.demo-image {
  //margin: 0 auto;
  height: 250px;
  width: 250px;
}
</style>

