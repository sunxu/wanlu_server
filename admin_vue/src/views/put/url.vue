<template>
  <div class="app-container">
    <el-table
      v-loading="listLoading"
      :data="list"
      element-loading-text="Loading"
      border
      fit
      highlight-current-row
      :stripe="true"
    >
      <el-table-column align="center" :label="appName">
        <el-table-column align="center" label="投放状态" width="80">
          <el-table-column align="center" label="投放屏蔽" width="80">
            <el-table-column align="center" label="平台" width="80">
              <template slot-scope="scope">
                {{ scope.row.name }}
              </template>
            </el-table-column>
          </el-table-column>
        </el-table-column>
        <el-table-column align="center" width="80">
          <template slot="header" slot-scope="scope">
            <el-tag v-if="put.put_status === 0" effect="dark" type="danger">{{ put.put_status_name }}</el-tag>
            <el-tag v-if="put.put_status === 1" effect="dark" type="success">{{ put.put_status_name }}</el-tag>
            <el-tag v-if="put.put_status === 2" effect="dark">{{ put.put_status_name }}</el-tag>
          </template>
          <el-table-column align="left" width="80">
            <template slot="header" slot-scope="scope">
              <el-form label-position="left" :model="save_data">
                <el-form-item>
                  <el-checkbox-group v-model="save_data.channel">
                    <el-checkbox-button v-for="(value,key) in channel_map" :label="key"> {{ value }} </el-checkbox-button>
                  </el-checkbox-group>
                </el-form-item>
              </el-form>
            </template>
            <el-table-column label="监测地址" header-align="center">
              <template slot-scope="scope">
                {{ scope.row.url }}
              </template>
            </el-table-column>
          </el-table-column>
        </el-table-column>
        <el-table-column align="center" label="" width="80">
          <template slot="header">
              <el-button type="primary" size="mini" @click="putStatus">修改</el-button>
            </template>
          <el-table-column align="center" label="" width="80">
            <template slot="header">
              <el-button type="primary" size="mini" @click="saveData">保存</el-button>
            </template>
            <el-table-column align="center" label="操作" width="80">
              <template slot-scope="scope">
                <el-button type="success" size="mini" @click="copy(scope.row.url)">复制</el-button>
              </template>
            </el-table-column>
          </el-table-column>
        </el-table-column>
      </el-table-column>
    </el-table>
  </div>
</template>

<script>
import { getAppCode } from '@/utils/auth'
import { Message } from 'element-ui'
import { getUrl } from '@/api/put/advertiser'
import { blockSave, putStatus } from '@/api/setting/app'

export default {
  data() {
    return {
      channel_map: _.clone(this.$global.CHANNEL_MAP),
      save_dialog_visible: false,
      save_loading: false,
      save_data: {
        channel: []
      },
      put: {
        'put_status': 0,
        'put_status_name': ''
      },
      list: null,
      appName: null,
      listLoading: false
    }
  },
  created() {
    this.fetchData()
  },
  methods: {
    fetchData() {
      this.listLoading = true
      getUrl(getAppCode()).then(response => {
        this.list = response.data.list
        this.appName = response.data.app_name
        this.put = response.data.put
        this.save_data.channel = response.data.channel.map(o => {
          return String(o)
        })
        console.log(this.channel)
        this.list.map(row => {

        })
        this.listLoading = false
      }).catch(() => {
        this.listLoading = false
      })
    },
    copy(data) {
      const url = data
      const oInput = document.createElement('input')
      oInput.value = url
      document.body.appendChild(oInput)
      oInput.select()
      document.execCommand('Copy')
      Message({
        message: '复制成功',
        type: 'success'
      })
      oInput.remove()
    },
    updateShow(index) {
      this.save_data = JSON.parse(JSON.stringify(this.list[index]))
      this.save_data.channel = this.save_data.channel.map(o => {
        return String(o)
      })
      this.save_dialog_visible = true
    },
    putStatus() {
      this.save_loading = true
      putStatus(getAppCode(), this.save_data).then(() => {
        Message({
          message: '修改成功',
          type: 'success',
          duration: 5 * 1000
        })
        this.save_loading = false
        this.fetchData()
      }).catch(() => {
        this.save_loading = false
      })
    },
    saveData() {
      this.save_loading = true
      blockSave(getAppCode(), this.save_data).then(() => {
        Message({
          message: '保存成功',
          type: 'success',
          duration: 5 * 1000
        })
        this.save_loading = false
        this.fetchData()
      }).catch(() => {
        this.save_loading = false
      })
    }
  }
}
</script>
<style>
.el-row {
  margin-bottom: 10px;
}

.el-col-span {
  display: inline-block;
  width: 80px;
  padding: 6px 0;
  font-size: 14px;
}
</style>
