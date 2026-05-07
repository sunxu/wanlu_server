<template>
  <div class="app-container">
    <el-row>
      <el-col :span="8">
        <span class="el-col-span">日期：</span>
        <el-date-picker
          v-model="search.date_picker"
          type="daterange"
          align="right"
          :clearable="false"
          unlink-panels
          range-separator="~"
          value-format="yyyy-MM-dd"
          start-placeholder="开始日期"
          end-placeholder="结束日期"
          style="width: 240px;"
          :picker-options="pickerOptions">
        </el-date-picker>
      </el-col>
      <el-col :span="8">
        <span class="el-col-span">投放渠道：</span>
        <el-select v-model="search.source" placeholder="请选择投放渠道" clearable style="width: 240px;">
          <el-option
            v-for="(value,key) in $global.SOURCE_MAP"
            :label="value"
            :value="key"
          ></el-option>
        </el-select>
      </el-col>
      <el-col :span="8">
        <span class="el-col-span">投放方式：</span>
        <el-select v-model="search.launch_type" placeholder="请选择投放方式" clearable style="width: 240px;">
          <el-option
            v-for="(value,key) in $global.LAUNCH_TYPE_MAP"
            :label="value"
            :value="key"
          ></el-option>
        </el-select>
      </el-col>
    </el-row>
    <el-row>
      <el-col :span="8">
        <span class="el-col-span">产品版本：</span>
        <el-input v-model="search.app_version" placeholder="请填写产品版本" clearable style="width: 240px"></el-input>
      </el-col>
      <el-col :span="8">
        <span class="el-col-span">广告主：</span>
        <el-select v-model="search.advertiser_id" :filterable="true" :multiple="true" :clearable="true" placeholder="全部"
                   style="width: 240px;">
          <el-option
            v-for="item in advertiser_list"
            :label="item.advertiser_id+' '+item.advertiser_name"
            :value="item.advertiser_id"
          ></el-option>
        </el-select>
      </el-col>

    </el-row>
    <el-row>
      <el-col :span="12">
        <el-radio-group v-model="search.type" @change="onSearch">
          <el-radio-button
            v-for="(value,key) in type_map"
            :label="key"
          >{{ value }}</el-radio-button>
        </el-radio-group>
      </el-col>
      <el-col :span="12">
        <el-button type="primary" icon="el-icon-download" @click="onExport" style="float: right">导出</el-button>
        <el-button type="primary" icon="el-icon-search" @click="onSearch" style="float: right; margin-right: 10px;">搜索
        </el-button>
      </el-col>
    </el-row>
    <el-table
      v-loading="listLoading"
      :data="list"
      element-loading-text="Loading"
      border
      fit
      highlight-current-row
      :stripe="true"
    >
      <el-table-column align="center" label="日期" width="100" fixed>
        <template slot-scope="scope">
          {{ scope.row.day }}
        </template>
      </el-table-column>
      <el-table-column label="设备数" align="center" fixed>
        <template slot-scope="scope">
          {{ scope.row.total_num }}
        </template>
      </el-table-column>
      <el-table-column label="小米(占比)" align="center" width="100">
        <template slot-scope="scope">
          <div>{{ scope.row.xiaomi_rate }}</div>
          <div>( {{ scope.row.xiaomi_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="8.0以下" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.xiaomi_lt8_rate }}</div>
          <div>( {{ scope.row.xiaomi_lt8_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="8.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.xiaomi_eq8_rate }}</div>
          <div>( {{ scope.row.xiaomi_eq8_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="9.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.xiaomi_eq9_rate }}</div>
          <div>( {{ scope.row.xiaomi_eq9_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="10.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.xiaomi_eq10_rate }}</div>
          <div>( {{ scope.row.xiaomi_eq10_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="11.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.xiaomi_ge11_rate }}</div>
          <div>( {{ scope.row.xiaomi_ge11_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="12.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.xiaomi_ge12_rate }}</div>
          <div>( {{ scope.row.xiaomi_ge12_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="13.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.xiaomi_ge13_rate }}</div>
          <div>( {{ scope.row.xiaomi_ge13_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="14.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.xiaomi_ge14_rate }}</div>
          <div>( {{ scope.row.xiaomi_ge14_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="15.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.xiaomi_ge15_rate }}</div>
          <div>( {{ scope.row.xiaomi_ge15_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="16.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.xiaomi_ge16_rate }}</div>
          <div>( {{ scope.row.xiaomi_ge16_num }} )</div>
        </template>
      </el-table-column>

      <el-table-column label="华为(占比)" align="center" width="100">
        <template slot-scope="scope">
          <div>{{ scope.row.huawei_rate }}</div>
          <div>( {{ scope.row.huawei_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="8.0以下" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.huawei_lt8_rate }}</div>
          <div>( {{ scope.row.huawei_lt8_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="8.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.huawei_eq8_rate }}</div>
          <div>( {{ scope.row.huawei_eq8_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="9.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.huawei_eq9_rate }}</div>
          <div>( {{ scope.row.huawei_eq9_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="10.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.huawei_eq10_rate }}</div>
          <div>( {{ scope.row.huawei_eq10_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="11.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.huawei_ge11_rate }}</div>
          <div>( {{ scope.row.huawei_ge11_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="12.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.huawei_ge12_rate }}</div>
          <div>( {{ scope.row.huawei_ge12_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="13.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.huawei_ge13_rate }}</div>
          <div>( {{ scope.row.huawei_ge13_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="14.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.huawei_ge14_rate }}</div>
          <div>( {{ scope.row.huawei_ge14_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="15.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.huawei_ge15_rate }}</div>
          <div>( {{ scope.row.huawei_ge15_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="16.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.huawei_ge16_rate }}</div>
          <div>( {{ scope.row.huawei_ge16_num }} )</div>
        </template>
      </el-table-column>

      <el-table-column label="OPPO(占比)" align="center" width="100">
        <template slot-scope="scope">
          <div>{{ scope.row.oppo_rate }}</div>
          <div>( {{ scope.row.oppo_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="8.0以下" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.oppo_lt8_rate }}</div>
          <div>( {{ scope.row.oppo_lt8_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="8.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.oppo_eq8_rate }}</div>
          <div>( {{ scope.row.oppo_eq8_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="9.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.oppo_eq9_rate }}</div>
          <div>( {{ scope.row.oppo_eq9_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="10.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.oppo_eq10_rate }}</div>
          <div>( {{ scope.row.oppo_eq10_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="11.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.oppo_ge11_rate }}</div>
          <div>( {{ scope.row.oppo_ge11_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="12.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.oppo_ge12_rate }}</div>
          <div>( {{ scope.row.oppo_ge12_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="13.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.oppo_ge13_rate }}</div>
          <div>( {{ scope.row.oppo_ge13_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="14.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.oppo_ge14_rate }}</div>
          <div>( {{ scope.row.oppo_ge14_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="15.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.oppo_ge15_rate }}</div>
          <div>( {{ scope.row.oppo_ge15_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="16.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.oppo_ge16_rate }}</div>
          <div>( {{ scope.row.oppo_ge16_num }} )</div>
        </template>
      </el-table-column>

      <el-table-column label="VIVO(占比)" align="center" width="100">
        <template slot-scope="scope">
          <div>{{ scope.row.vivo_rate }}</div>
          <div>( {{ scope.row.vivo_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="8.0以下" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.vivo_lt8_rate }}</div>
          <div>( {{ scope.row.vivo_lt8_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="8.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.vivo_eq8_rate }}</div>
          <div>( {{ scope.row.vivo_eq8_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="9.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.vivo_eq9_rate }}</div>
          <div>( {{ scope.row.vivo_eq9_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="10.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.vivo_eq10_rate }}</div>
          <div>( {{ scope.row.vivo_eq10_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="11.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.vivo_ge11_rate }}</div>
          <div>( {{ scope.row.vivo_ge11_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="12.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.vivo_ge12_rate }}</div>
          <div>( {{ scope.row.vivo_ge12_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="13.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.vivo_ge13_rate }}</div>
          <div>( {{ scope.row.vivo_ge13_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="14.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.vivo_ge14_rate }}</div>
          <div>( {{ scope.row.vivo_ge14_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="15.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.vivo_ge15_rate }}</div>
          <div>( {{ scope.row.vivo_ge15_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="16.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.vivo_ge16_rate }}</div>
          <div>( {{ scope.row.vivo_ge16_num }} )</div>
        </template>
      </el-table-column>

      <el-table-column label="其他(占比)" align="center" width="100">
        <template slot-scope="scope">
          <div>{{ scope.row.other_rate }}</div>
          <div>( {{ scope.row.other_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="8.0以下" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.other_lt8_rate }}</div>
          <div>( {{ scope.row.other_lt8_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="8.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.other_eq8_rate }}</div>
          <div>( {{ scope.row.other_eq8_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="9.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.other_eq9_rate }}</div>
          <div>( {{ scope.row.other_eq9_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="10.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.other_eq10_rate }}</div>
          <div>( {{ scope.row.other_eq10_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="11.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.other_ge11_rate }}</div>
          <div>( {{ scope.row.other_ge11_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="12.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.other_ge12_rate }}</div>
          <div>( {{ scope.row.other_ge12_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="13.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.other_ge13_rate }}</div>
          <div>( {{ scope.row.other_ge13_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="14.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.other_ge14_rate }}</div>
          <div>( {{ scope.row.other_ge14_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="15.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.other_ge15_rate }}</div>
          <div>( {{ scope.row.other_ge15_num }} )</div>
        </template>
      </el-table-column>
      <el-table-column label="16.0+" align="center">
        <template slot-scope="scope">
          <div>{{ scope.row.other_ge16_rate }}</div>
          <div>( {{ scope.row.other_ge16_num }} )</div>
        </template>
      </el-table-column>

    </el-table>
  </div>
</template>
<style>
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
<script>
import {getList} from '@/api/product/vendor'
import {getAdvertiser} from '@/api/product/common'
import shortcuts from '@/utils/date-picker'
import {parseTime} from '@/utils'
import {getCurrentApp} from '@/utils/auth'
import {Message} from 'element-ui'

export default {
  data() {
    return {
      pickerOptions: {
        shortcuts
      },
      search: {
        date_picker: this.defaultDate(),
        source: '',
        launch_type: '',
        app_version: '',
        advertiser_id: '',
        app_code: '',
        type: 'install'
      },
      app_info: getCurrentApp(),
      type_map: {
        'install': '新增安装',
        'activate': '新增激活',
        'dau': 'DAU',
        'deu': 'DEU'
      },
      advertiser_list: [],
      list: null,
      listLoading: false
    }
  },
  created() {
    this.search.app_code = this.app_info.app_code
    this.onSearch()
    this.fetchAdvertiser()
  },
  methods: {
    defaultDate() {//默认时间 - 过去7天
      const end = new Date()
      const start = new Date()
      end.setTime(end.getTime())
      start.setTime(start.getTime() - 86400 * 1000 * 7)
      const format = '{y}-{m}-{d}'
      return [parseTime(start, format), parseTime(end, format)]
    },
    onSearch() {
      if (!this.search.app_code) {
        Message({
          message: '请选择应用',
          type: 'error',
          duration: 5 * 1000
        })
        return
      }
      this.fetchData()
    },
    onExport() {
      if (!this.list) {
        Message({
          message: '暂无数据',
          type: 'error',
          duration: 5 * 1000
        })
        return
      }
      import('@/utils/Export2Excel').then(excel => {
        const tHeader = ['日期', '设备数', '华为数', '占比', '8.0以下', '8+', '9+', '10+', '11+', '12+', '13+', '14+', '15+', '16+', '小米数', '占比', '8.0以下', '8+', '9+', '10+', '11+','12+', '13+', '14+', '15+', '16+', 'OPPO数', '占比', '8.0以下', '8+', '9+', '10+', '11+','12+', '13+', '14+', '15+', '16+', 'VIVO数', '占比', '8.0以下', '8+', '9+', '10+', '11+', '12+', '13+', '14+', '15+', '16+', '其他数', '占比', '8.0以下', '8+', '9+', '10+', '11+', '12+', '13+', '14+', '15+', '16+']
        const filterVal = ['day', 'total_num',
          'huawei_num', 'huawei_rate', 'huawei_lt8_num', 'huawei_eq8_num', 'huawei_eq9_num', 'huawei_eq10_num', 'huawei_ge11_num','huawei_ge12_num','huawei_ge13_num','huawei_ge14_num','huawei_ge15_num','huawei_ge16_num',
          'xiaomi_num', 'xiaomi_rate', 'xiaomi_lt8_num', 'xiaomi_eq8_num', 'xiaomi_eq9_num', 'xiaomi_eq10_num', 'xiaomi_ge11_num','xiaomi_ge12_num','xiaomi_ge13_num','xiaomi_ge14_num','xiaomi_ge15_num','xiaomi_ge16_num',
          'oppo_num', 'oppo_rate', 'oppo_lt8_num', 'oppo_eq8_num', 'oppo_eq9_num', 'oppo_eq10_num', 'oppo_ge11_num','oppo_ge12_num','oppo_ge13_num','oppo_ge14_num','oppo_ge15_num','oppo_ge16_num',
          'vivo_num', 'vivo_rate', 'vivo_lt8_num', 'vivo_eq8_num', 'vivo_eq9_num', 'vivo_eq10_num', 'vivo_ge11_num','vivo_ge12_num','vivo_ge13_num','vivo_ge14_num','vivo_ge15_num','vivo_ge16_num',
          'other_num', 'other_rate', 'other_lt8_num', 'other_eq8_num', 'other_eq9_num', 'other_eq10_num', 'other_ge11_num','other_ge12_num', 'other_ge13_num','other_ge14_num','other_ge15_num','other_ge16_num'
        ]
        const list = this.list
        const data = this.formatJson(filterVal, list)
        const type_name = this.type_map[this.search.type]
        const app_name = this.app_info.app_name
        const start = this.search.date_picker[0]
        const end = this.search.date_picker[1]
        const filename = app_name + '_厂商分布_' + type_name + '_' + start + '_' + end
        excel.export_json_to_excel({
          header: tHeader, //表头 必填
          data, //具体数据 必填
          filename: filename, //非必填
          autoWidth: true, //非必填
          bookType: 'csv' //非必填
        })
      })
    },
    formatJson(filterVal, jsonData) {
      return jsonData.map(v => filterVal.map(j => {
        if (j === 'timestamp') {
          return parseTime(v[j])
        } else {
          return v[j]
        }
      }))
    },
    fetchData() {
      this.listLoading = true
      getList(this.search).then(response => {
        this.list = response.data.list
        this.listLoading = false
      }).catch(() => {
        this.listLoading = false
      })
    },
    fetchAdvertiser() {
      getAdvertiser(this.search).then(response => {
        this.advertiser_list = response.data.list
      })
    }
  }
}
</script>
