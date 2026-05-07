<template>
  <div class="app-container">
    <div class="filter-container">
      <el-row>
        <el-col :span="8">
          <span class="el-col-span">日期：</span>
          <el-date-picker v-model="searchDate" type="daterange" style="width: 240px;" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期" :clearable="false"></el-date-picker>
        </el-col>
        <el-col :span="8">
          <span class="el-col-span">版本：</span>
          <el-input v-model="listQuery.version" placeholder="版本" size="small" style="width: 240px;" class="filter-item" />
        </el-col>
        <el-col :span="8">
          <span class="el-col-span">产品渠道：</span>
          <el-select v-model="listQuery.channel" placeholder="产品渠道" clearable style="width: 240px" class="filter-item">
            <el-option v-for="(value,key) in $global.CHANNEL_MAP" :label="value" :value="key" />
          </el-select>
        </el-col>
      </el-row>
      <el-row>
        <el-col :span="8">
          <el-radio-group v-model="listQuery.type" size="small" @change="handleFilter">
            <el-radio-button label="installed">新增安装</el-radio-button>
            <el-radio-button label="dau">DAU</el-radio-button>
          </el-radio-group>
        </el-col>
        <el-col :span="8">
          <el-button class="filter-item" type="primary" icon="el-icon-search" @click="handleFilter">
            搜索
          </el-button>
          <el-button class="filter-item" type="primary" icon="el-icon-download" :loading="downloadLoading" @click="handleDownload">
            导出
          </el-button>
        </el-col>
      </el-row>
    </div>
    <el-table
      v-loading="listLoading"
      :data="list"
      element-loading-text="Loading"
      border
      fit
      highlight-current-row
    >
      <el-table-column align="center" label="日期" prop="date" width="100" fixed></el-table-column>
      <el-table-column align="center" label="设备数" prop="device_num" width="80" fixed></el-table-column>
      <el-table-column align="center" label="小米">
        <el-table-column align="center" label="设备数" prop="xiaomi.device_num"></el-table-column>
        <el-table-column align="center" label="占比" :formatter="(row, column) => deviceRate(row, column, row.xiaomi.device_num)"></el-table-column>
        <el-table-column align="center" label="安卓 8 以下（分钟）" :formatter="(row, column) => msToMin(row, column, row.xiaomi.os_lt_8)"></el-table-column>
        <el-table-column align="center" label="安卓 8（分钟）" :formatter="(row, column) => msToMin(row, column, row.xiaomi.os_ge_8_lt_9)"></el-table-column>
        <el-table-column align="center" label="安卓 9（分钟）" :formatter="(row, column) => msToMin(row, column, row.xiaomi.os_ge_9_lt_10)"></el-table-column>
        <el-table-column align="center" label="安卓 10（分钟）" :formatter="(row, column) => msToMin(row, column, row.xiaomi.os_ge_10_lt_11)"></el-table-column>
        <el-table-column align="center" label="安卓 11（分钟）" :formatter="(row, column) => msToMin(row, column, row.xiaomi.os_ge_11_lt_12)"></el-table-column>
        <el-table-column align="center" label="安卓 12（分钟）" :formatter="(row, column) => msToMin(row, column, row.xiaomi.os_ge_12_lt_13)"></el-table-column>
        <el-table-column align="center" label="安卓 13（分钟）" :formatter="(row, column) => msToMin(row, column, row.xiaomi.os_ge_13_lt_14)"></el-table-column>
        <el-table-column align="center" label="安卓 14（分钟）" :formatter="(row, column) => msToMin(row, column, row.xiaomi.os_ge_14_lt_15)"></el-table-column>
        <el-table-column align="center" label="安卓 15（分钟）" :formatter="(row, column) => msToMin(row, column, row.xiaomi.os_ge_15_lt_16)"></el-table-column>
        <el-table-column align="center" label="安卓 16（分钟）" :formatter="(row, column) => msToMin(row, column, row.xiaomi.os_ge_16)"></el-table-column>
      </el-table-column>
      <el-table-column align="center" label="华为">
        <el-table-column align="center" label="设备数" prop="huawei.device_num"></el-table-column>
        <el-table-column align="center" label="占比" :formatter="(row, column) => deviceRate(row, column, row.huawei.device_num)"></el-table-column>
        <el-table-column align="center" label="安卓 8 以下（分钟）" :formatter="(row, column) => msToMin(row, column, row.huawei.os_lt_8)"></el-table-column>
        <el-table-column align="center" label="安卓 8（分钟）" :formatter="(row, column) => msToMin(row, column, row.huawei.os_ge_8_lt_9)"></el-table-column>
        <el-table-column align="center" label="安卓 9（分钟）" :formatter="(row, column) => msToMin(row, column, row.huawei.os_ge_9_lt_10)"></el-table-column>
        <el-table-column align="center" label="安卓 10（分钟）" :formatter="(row, column) => msToMin(row, column, row.huawei.os_ge_10_lt_11)"></el-table-column>
        <el-table-column align="center" label="安卓 11（分钟）" :formatter="(row, column) => msToMin(row, column, row.huawei.os_ge_11_lt_12)"></el-table-column>
        <el-table-column align="center" label="安卓 12（分钟）" :formatter="(row, column) => msToMin(row, column, row.huawei.os_ge_12_lt_13)"></el-table-column>
        <el-table-column align="center" label="安卓 13（分钟）" :formatter="(row, column) => msToMin(row, column, row.huawei.os_ge_13_lt_14)"></el-table-column>
        <el-table-column align="center" label="安卓 14（分钟）" :formatter="(row, column) => msToMin(row, column, row.huawei.os_ge_14_lt_15)"></el-table-column>
        <el-table-column align="center" label="安卓 15（分钟）" :formatter="(row, column) => msToMin(row, column, row.huawei.os_ge_15_lt_16)"></el-table-column>
        <el-table-column align="center" label="安卓 16（分钟）" :formatter="(row, column) => msToMin(row, column, row.huawei.os_ge_16)"></el-table-column>
      </el-table-column>
      <el-table-column align="center" label="OPPO">
        <el-table-column align="center" label="设备数" prop="oppo.device_num"></el-table-column>
        <el-table-column align="center" label="占比" :formatter="(row, column) => deviceRate(row, column, row.oppo.device_num)"></el-table-column>
        <el-table-column align="center" label="安卓 8 以下（分钟）" :formatter="(row, column) => msToMin(row, column, row.oppo.os_lt_8)"></el-table-column>
        <el-table-column align="center" label="安卓 8（分钟）" :formatter="(row, column) => msToMin(row, column, row.oppo.os_ge_8_lt_9)"></el-table-column>
        <el-table-column align="center" label="安卓 9（分钟）" :formatter="(row, column) => msToMin(row, column, row.oppo.os_ge_9_lt_10)"></el-table-column>
        <el-table-column align="center" label="安卓 10（分钟）" :formatter="(row, column) => msToMin(row, column, row.oppo.os_ge_10_lt_11)"></el-table-column>
        <el-table-column align="center" label="安卓 11（分钟）" :formatter="(row, column) => msToMin(row, column, row.oppo.os_ge_11_lt_12)"></el-table-column>
        <el-table-column align="center" label="安卓 12（分钟）" :formatter="(row, column) => msToMin(row, column, row.oppo.os_ge_12_lt_13)"></el-table-column>
        <el-table-column align="center" label="安卓 13（分钟）" :formatter="(row, column) => msToMin(row, column, row.oppo.os_ge_13_lt_14)"></el-table-column>
        <el-table-column align="center" label="安卓 14（分钟）" :formatter="(row, column) => msToMin(row, column, row.oppo.os_ge_14_lt_15)"></el-table-column>
        <el-table-column align="center" label="安卓 15（分钟）" :formatter="(row, column) => msToMin(row, column, row.oppo.os_ge_15_lt_16)"></el-table-column>
        <el-table-column align="center" label="安卓 16（分钟）" :formatter="(row, column) => msToMin(row, column, row.oppo.os_ge_16)"></el-table-column>
      </el-table-column>
      <el-table-column align="center" label="VIVO">
        <el-table-column align="center" label="设备数" prop="vivo.device_num"></el-table-column>
        <el-table-column align="center" label="占比" :formatter="(row, column) => deviceRate(row, column, row.vivo.device_num)"></el-table-column>
        <el-table-column align="center" label="安卓 8 以下（分钟）" :formatter="(row, column) => msToMin(row, column, row.vivo.os_lt_8)"></el-table-column>
        <el-table-column align="center" label="安卓 8（分钟）" :formatter="(row, column) => msToMin(row, column, row.vivo.os_ge_8_lt_9)"></el-table-column>
        <el-table-column align="center" label="安卓 9（分钟）" :formatter="(row, column) => msToMin(row, column, row.vivo.os_ge_9_lt_10)"></el-table-column>
        <el-table-column align="center" label="安卓 10（分钟）" :formatter="(row, column) => msToMin(row, column, row.vivo.os_ge_10_lt_11)"></el-table-column>
        <el-table-column align="center" label="安卓 11（分钟）" :formatter="(row, column) => msToMin(row, column, row.vivo.os_ge_11_lt_12)"></el-table-column>
        <el-table-column align="center" label="安卓 12（分钟）" :formatter="(row, column) => msToMin(row, column, row.vivo.os_ge_12_lt_13)"></el-table-column>
        <el-table-column align="center" label="安卓 13（分钟）" :formatter="(row, column) => msToMin(row, column, row.vivo.os_ge_13_lt_14)"></el-table-column>
        <el-table-column align="center" label="安卓 14（分钟）" :formatter="(row, column) => msToMin(row, column, row.vivo.os_ge_14_lt_15)"></el-table-column>
        <el-table-column align="center" label="安卓 15（分钟）" :formatter="(row, column) => msToMin(row, column, row.vivo.os_ge_15_lt_16)"></el-table-column>
        <el-table-column align="center" label="安卓 16（分钟）" :formatter="(row, column) => msToMin(row, column, row.vivo.os_ge_16)"></el-table-column>
      </el-table-column>
      <el-table-column align="center" label="其他">
        <el-table-column align="center" label="设备数" prop="other.device_num"></el-table-column>
        <el-table-column align="center" label="占比" :formatter="(row, column) => deviceRate(row, column, row.other.device_num)"></el-table-column>
        <el-table-column align="center" label="安卓 8 以下（分钟）" :formatter="(row, column) => msToMin(row, column, row.other.os_lt_8)"></el-table-column>
        <el-table-column align="center" label="安卓 8（分钟）" :formatter="(row, column) => msToMin(row, column, row.other.os_ge_8_lt_9)"></el-table-column>
        <el-table-column align="center" label="安卓 9（分钟）" :formatter="(row, column) => msToMin(row, column, row.other.os_ge_9_lt_10)"></el-table-column>
        <el-table-column align="center" label="安卓 10（分钟）" :formatter="(row, column) => msToMin(row, column, row.other.os_ge_10_lt_11)"></el-table-column>
        <el-table-column align="center" label="安卓 11（分钟）" :formatter="(row, column) => msToMin(row, column, row.other.os_ge_11_lt_12)"></el-table-column>
        <el-table-column align="center" label="安卓 12（分钟）" :formatter="(row, column) => msToMin(row, column, row.other.os_ge_12_lt_13)"></el-table-column>
        <el-table-column align="center" label="安卓 13（分钟）" :formatter="(row, column) => msToMin(row, column, row.other.os_ge_13_lt_14)"></el-table-column>
        <el-table-column align="center" label="安卓 14（分钟）" :formatter="(row, column) => msToMin(row, column, row.other.os_ge_14_lt_15)"></el-table-column>
        <el-table-column align="center" label="安卓 15（分钟）" :formatter="(row, column) => msToMin(row, column, row.other.os_ge_15_lt_16)"></el-table-column>
        <el-table-column align="center" label="安卓 16（分钟）" :formatter="(row, column) => msToMin(row, column, row.other.os_ge_16)"></el-table-column>
      </el-table-column>
    </el-table>
  </div>
</template>

<script>
import { getAppCode } from '@/utils/auth'
import { parseTime } from '@/utils'
import { Message } from 'element-ui'
import { liveList } from '@/api/cash/list'

export default {
  label: '心跳数据',
  name: 'Live',
  data() {
    return {
      searchDate: undefined,
      listLoading: false,
      total: 0,
      list: undefined,
      downloadLoading: false,
      listQuery: {
        page: 1,
        per_page: 10,
        date_begin: undefined,
        date_end: undefined,
        version: undefined,
        channel: undefined,
        type: 'installed'
      }
    }
  },
  created() {
    this.searchDate = this.defaultDate()
  },
  mounted() {
    this.fetchData()
  },
  methods: {
    defaultDate() { // 默认时间 - 过去7天
      const end = new Date()
      const start = new Date()
      end.setTime(end.getTime())
      start.setTime(start.getTime() - 86400 * 1000 * 7)
      const format = '{y}-{m}-{d}'
      return [parseTime(start, format), parseTime(end, format)]
    },
    fetchData() {
      this.listLoading = true
      this.listQuery.date_begin = parseTime(this.searchDate[0], '{y}-{m}-{d}')
      this.listQuery.date_end = parseTime(this.searchDate[1], '{y}-{m}-{d}')
      liveList(getAppCode(), this.listQuery.type, this.listQuery).then(response => {
        this.list = response.data.records
        this.listLoading = false
      })
    },
    handleFilter() {
      this.listQuery.page = 1
      this.fetchData()
    },
    deviceRate(row, column, device_num) {
      return (row.device_num > 0 ? Math.floor(device_num / row.device_num * 10000) / 100 : 0) + '%'
    },
    msToMin(row, column, liveTime) {
      if (liveTime.num <= 0) {
        return 0
      }
      const time = liveTime.time / liveTime.num / 1000 / 60
      return Math.floor(time * 100) / 100
    },
    handleDownload() {
      this.downloadLoading = true
      const that = this
      liveList(getAppCode(), this.listQuery.type, this.listQuery).then(response => {
        import('@/vendor/Export2Excel').then(excel => {
          const tHeader = [
            '日期', '设备数',
            '设备数（华为）', '占比（华为)', '安卓8以下（分钟）（华为）', '安卓8（分钟）（华为）', '安卓9（分钟）（华为）',
            '安卓10（分钟）（华为）', '安卓11（分钟）（华为）', '安卓12（分钟）（华为）', '安卓13（分钟）（华为）', '安卓14（分钟）（华为）', '安卓15（分钟）（华为）', '安卓16（分钟）（华为）',
            '设备数（OPPO）', '占比（OPPO)', '安卓8以下（分钟）（OPPO）', '安卓8（分钟）（OPPO）', '安卓9（分钟）（OPPO）',
            '安卓10（分钟）（OPPO）', '安卓11（分钟）（OPPO）', '安卓12（分钟）（OPPO）', '安卓13（分钟）（OPPO）', '安卓14（分钟）（OPPO）', '安卓15（分钟）（OPPO）', '安卓16（分钟）（OPPO）',
            '设备数（VIVO）', '占比（VIVO)', '安卓8以下（分钟）（VIVO）', '安卓8（分钟）（VIVO）', '安卓9（分钟）（VIVO）',
            '安卓10（分钟）（VIVO）', '安卓11（分钟）（VIVO）', '安卓12（分钟）（VIVO）', '安卓13（分钟）（VIVO）', '安卓14（分钟）（VIVO）', '安卓15（分钟）（VIVO）', '安卓16（分钟）（VIVO）',
            '设备数（小米）', '占比（小米)', '安卓8以下（分钟）（小米）', '安卓8（分钟）（小米）', '安卓9（分钟）（小米）',
            '安卓10（分钟）（小米）', '安卓11（分钟）（小米）', '安卓12（分钟）（小米）', '安卓13（分钟）（小米）', '安卓14（分钟）（小米）', '安卓15（分钟）（小米）', '安卓16（分钟）（小米）',
            '设备数（其他）', '占比（其他)', '安卓8以下（分钟）（其他）', '安卓8（分钟）（其他）', '安卓9（分钟）（其他）',
            '安卓10（分钟）（其他）', '安卓11（分钟）（其他）', '安卓12（分钟）（其他）', '安卓13（分钟）（其他）', '安卓14（分钟）（其他）', '安卓15（分钟）（其他）', '安卓16（分钟）（其他）'
          ]
          const rateFunc = function(num, totalNum) {
            return (totalNum > 0 ? Math.floor(num / totalNum * 10000) / 100 : 0) + '%'
          }
          const minFunc = function(liveTime) {
            if (liveTime.num <= 0) {
              return 0
            }
            const time = liveTime.time / liveTime.num / 1000 / 60
            return Math.floor(time * 100) / 100
          }
          const data = response.data.records.map(function(item) {
            return [
              item.date, item.device_num,
              item.huawei.device_num, rateFunc(item.huawei.device_num, item.device_num), minFunc(item.huawei.os_lt_8),
              minFunc(item.huawei.os_ge_8_lt_9), minFunc(item.huawei.os_ge_9_lt_10), minFunc(item.huawei.os_ge_10_lt_11),
              minFunc(item.huawei.os_ge_11_lt_12), minFunc(item.huawei.os_ge_12_lt_13), minFunc(item.huawei.os_ge_13_lt_14),
              minFunc(item.huawei.os_ge_14_lt_15), minFunc(item.huawei.os_ge_15_lt_16), minFunc(item.huawei.os_ge_16),
              item.oppo.device_num, rateFunc(item.oppo.device_num, item.device_num), minFunc(item.oppo.os_lt_8),
              minFunc(item.oppo.os_ge_8_lt_9), minFunc(item.oppo.os_ge_9_lt_10), minFunc(item.oppo.os_ge_10_lt_11),
              minFunc(item.oppo.os_ge_11_lt_12), minFunc(item.oppo.os_ge_12_lt_13), minFunc(item.oppo.os_ge_13_lt_14),
              minFunc(item.oppo.os_ge_14_lt_15), minFunc(item.oppo.os_ge_15_lt_16), minFunc(item.oppo.os_ge_16),
              item.vivo.device_num, rateFunc(item.vivo.device_num, item.device_num), minFunc(item.vivo.os_lt_8),
              minFunc(item.vivo.os_ge_8_lt_9), minFunc(item.vivo.os_ge_9_lt_10), minFunc(item.vivo.os_ge_10_lt_11),
              minFunc(item.vivo.os_ge_11_lt_12), minFunc(item.vivo.os_ge_12_lt_13), minFunc(item.vivo.os_ge_13),
              item.xiaomi.device_num, rateFunc(item.xiaomi.device_num, item.device_num), minFunc(item.xiaomi.os_lt_8),
              minFunc(item.xiaomi.os_ge_8_lt_9), minFunc(item.xiaomi.os_ge_9_lt_10), minFunc(item.xiaomi.os_ge_10_lt_11),
              minFunc(item.xiaomi.os_ge_11_lt_12), minFunc(item.xiaomi.os_ge_12_lt_13), minFunc(item.xiaomi.os_ge_13_lt_14),
              minFunc(item.xiaomi.os_ge_14_lt_15), minFunc(item.xiaomi.os_ge_15_lt_16), minFunc(item.xiaomi.os_ge_16),
              item.other.device_num, rateFunc(item.other.device_num, item.device_num), minFunc(item.other.os_lt_8),
              minFunc(item.other.os_ge_8_lt_9), minFunc(item.other.os_ge_9_lt_10), minFunc(item.other.os_ge_10_lt_11),
              minFunc(item.other.os_ge_11_lt_12), minFunc(item.other.os_ge_12_lt_13), minFunc(item.other.os_ge_13_lt_14),
              minFunc(item.other.os_ge_14_lt_15), minFunc(item.other.os_ge_15_lt_16), minFunc(item.other.os_ge_16)
            ]
          })
          if (data.length <= 0) {
            that.downloadLoading = false
            Message({
              showClose: true,
              message: '没有导出的数据',
              type: 'warning',
              duration: 5 * 1000
            })
            return
          }

          const date = new Date()
          const time = date.getFullYear() + '' + (date.getMonth() + 1) + '' + date.getDate() + '' + date.getHours() + '' + date.getMinutes() + '' + date.getSeconds()
          excel.export_json_to_excel({
            header: tHeader,
            data,
            filename: '心跳数据-' + time
          })
          that.downloadLoading = false
        })
      })
    }
  }
}
</script>

<style scoped>
.el-row {
  margin-bottom: 10px;
}

.el-radio {
  margin: 0
}

.el-col-span {
  display: inline-block;
  width: 80px;
  padding: 6px 0;
  font-size: 14px;
}
</style>
