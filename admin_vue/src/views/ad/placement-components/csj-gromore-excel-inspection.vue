<template>
  <el-dialog :visible.sync="dialogFormVisible" title="确认导入数据" width="90%" :before-close="cancelUpload">
    <el-table
      :data="list"
      element-loading-text="Loading"
      border
      fit
      highlight-current-row
    >
      <el-table-column label="广告位 ID"  align="center">
        <template slot-scope="scope">
          <span v-show="!scope.row.editFlag">{{ scope.row.ad_unit_id }}</span>
          <el-input
            v-show="scope.row.editFlag"
            v-model="scope.row.ad_unit_id"
          >
          </el-input>
        </template>
      </el-table-column>

      <el-table-column label="竞价类型"  align="center">
        <template slot-scope="scope">
          <span v-show="!scope.row.editFlag">{{ columnMap.biddingType[scope.row.bidding_type] }}</span>
          <el-select v-model="scope.row.bidding_type" v-show="scope.row.editFlag" @change="handleChange($event, scope.$index)" placeholder="选择竞价类型">
            <el-option
              v-for="(value, key) in columnMap.biddingType"
              :key="key"
              :label="value"
              :value="key"
            >
            </el-option>
          </el-select>
        </template>
      </el-table-column>

      <el-table-column label="代码位 ID"  align="center">
        <template slot-scope="scope">
          <span v-show="!scope.row.editFlag">{{ scope.row.ad_slot_id }}</span>
          <el-input
            v-show="scope.row.editFlag"
            v-model="scope.row.ad_slot_id"
          >
          </el-input>
        </template>
      </el-table-column>

      <el-table-column label="代码位名称"  align="center">
        <template slot-scope="scope">
          <span v-show="!scope.row.editFlag">{{ scope.row.ad_slot_name }}</span>
          <el-input
            v-show="scope.row.editFlag"
            v-model="scope.row.ad_slot_name"
          >
          </el-input>
        </template>
      </el-table-column>

      <el-table-column label="广告网络"  align="center">
        <template slot-scope="scope">
          <span v-show="!scope.row.editFlag">{{ networkName(scope.row.network) }}</span>
          <el-select v-model="scope.row.network" v-show="scope.row.editFlag" @change="handleChange($event, scope.$index)" placeholder="选择广告网络">
            <el-option
              v-for="item in networks"
              :key="item.network_id"
              :label="item.network_name"
              :value="item.network_id"
            >
            </el-option>
          </el-select>
        </template>
      </el-table-column>

      <el-table-column label="代码位价格"  align="center">
        <template slot-scope="scope">
          <span v-show="!scope.row.editFlag">{{ scope.row.price }}</span>
          <el-input
            v-show="scope.row.editFlag"
            v-model="scope.row.price"
          >
          </el-input>
        </template>
      </el-table-column>

      <el-table-column label="排序方式"  align="center">
        <template slot-scope="scope">
          <span v-show="!scope.row.editFlag">{{ scope.row.sort_type?columnMap.sortType[scope.row.sort_type]:'' }}</span>
          <el-select v-model="scope.row.sort_type" v-show="scope.row.editFlag" @change="handleChange($event, scope.$index)" placeholder="选择排序方式">
            <el-option
              v-for="(value, key) in columnMap.sortType"
              :key="key"
              :label="value"
              :value="key"
              clearable
            >
            </el-option>
          </el-select>
        </template>
      </el-table-column>

      <el-table-column label="状态"  align="center">
        <template slot-scope="scope">
          <span v-show="!scope.row.editFlag">{{ columnMap.status[scope.row.status] }}</span>
          <el-select v-model="scope.row.status" v-show="scope.row.editFlag" @change="handleChange($event, scope.$index)" placeholder="选择状态">
            <el-option
              v-for="(value, key) in columnMap.status"
              :key="key"
              :label="value"
              :value="key"
            >
            </el-option>
          </el-select>
        </template>
      </el-table-column>

      <el-table-column label="渲染类型"  align="center">
        <template slot-scope="scope">
          <span v-show="!scope.row.editFlag">{{ columnMap.originType[scope.row.origin_type] }}</span>
          <el-select v-model="scope.row.origin_type" v-show="scope.row.editFlag" @change="handleChange($event, scope.$index)" placeholder="选择渲染类型">
            <el-option
              v-for="(value, key) in columnMap.originType"
              :key="key"
              :label="value"
              :value="key"
            >
            </el-option>
          </el-select>
        </template>
      </el-table-column>

      <el-table-column label="代码位类型"  align="center">
        <template slot-scope="scope">
          <span v-show="!scope.row.editFlag">{{ scope.row.sub_ad_type?columnMap.subAdType[scope.row.sub_ad_type]:'' }}</span>
          <el-select v-model="scope.row.sub_ad_type" v-show="scope.row.editFlag" @change="handleChange($event, scope.$index)" placeholder="选择代码位类型">
            <el-option
              v-for="(value, key) in columnMap.subAdType"
              :key="key"
              :label="value"
              :value="key"
              clearable
            >
            </el-option>
          </el-select>
        </template>
      </el-table-column>

      <el-table-column label="自动排序开关"  align="center">
        <template slot-scope="scope">
          <span v-show="!scope.row.editFlag">{{ columnMap.smartSortSwitch[scope.row.smart_sort_switch] }}</span>
          <el-select v-model="scope.row.smart_sort_switch" v-show="scope.row.editFlag" @change="handleChange($event, scope.$index)" placeholder="选择自动排序开关">
            <el-option
              v-for="(value, key) in columnMap.smartSortSwitch"
              :key="key"
              :label="value"
              :value="key"
            >
            </el-option>
          </el-select>
        </template>
      </el-table-column>

      <el-table-column label="自动排序取数周期"  align="center">
        <template slot-scope="scope">
          <span v-show="!scope.row.editFlag">{{ scope.row.smart_sort_fetch_days }}</span>
          <el-input
            v-show="scope.row.editFlag"
            v-model="scope.row.smart_sort_fetch_days"
          >
          </el-input>
        </template>
      </el-table-column>

      <el-table-column label="操作" width="120" align="center">
        <template slot-scope="scope">
          <div style="display: flex;">
            <el-button
              size="small"
              type="text"
              icon="CircleCheck"
              v-show="scope.row.editFlag"
              @click="submit(scope.row, scope.$index)"
            >确定
            </el-button>
            <el-button
              size="small"
              icon="Edit"
              type="text"
              v-show="!scope.row.editFlag"
              @click="edit(scope.row, scope.$index)"> 编辑
            </el-button>
            <el-button
              size="small"
              icon="Delete"
              type="text"
              v-show="!scope.row.editFlag"
              @click="del(scope.$index)">删除
            </el-button>
          </div>
        </template>
      </el-table-column>
    </el-table>
    <div style="text-align:right;margin-top:20px;">
      <el-button type="primary" @click="confirm()">
        确认
      </el-button>
    </div>
  </el-dialog>
</template>

<script>
import { networksCsj } from '@/api/ad/placement'

export default {
  name: 'ExcelInspection',
  props: ['excelData', 'dialogFormVisible', 'excelCompanyId'],
  watch: {
    excelCompanyId(excelCompanyId) {
      networksCsj(excelCompanyId).then(response => {
        this.networks = response.data.records
      })
    }
  },
  computed: {
    list: function() {
      const l = []
      this.excelData.forEach(row => {
        row.editFlag = false
        row.bidding_type = row.bidding_type.toString()
        row.sort_type = row.sort_type.toString()
        row.status = row.status.toString()
        row.origin_type = row.origin_type.toString()
        row.sub_ad_type = row.sub_ad_type.toString()
        row.smart_sort_switch = row.smart_sort_switch.toString()
        l.push(row)
      })
      return l
    }
  },
  data() {
    return {
      columnMap: {
        biddingType: {
          0: '标准',
          1: '服务端竞价',
          2: '客户端竞价'
        },
        sortType: {
          1: '价格',
          2: '置顶',
          3: '兜底'
        },
        status: {
          1: '启用',
          4: '关闭'
        },
        originType: {
          0: '不区分',
          1: '优量汇模板渲染1.0',
          2: '自渲染',
          3: '优量汇模板渲染2.0'
        },
        subAdType: {
          1: '插屏或半屏',
          2: '全屏'
        },
        smartSortSwitch: {
          0: '不适用',
          1: '开启',
          2: '关闭'
        }
      },
      networks: []
    }
  },
  methods: {
    networkName(networkId) {
      const network = this.networks.filter((item) => {
        return networkId === item.network_id
      })
      if (network[0] && network[0].network_name) {
        return network[0].network_name
      }
      return ''
    },
    handleChange(event, index) {
      const row = this.list[index]
      row.editFlag = true
      this.$set(this.list, index, row)
    },
    confirm() {
      this.$emit('saveExcelData', { data: this.list })
    },
    cancelUpload() {
      this.$emit('cancelUpload')
    },
    submit(row, index) {
      console.log(row)
      row.editFlag = false
      this.$set(this.list, index, row)
    },
    edit(row, index) {
      row.editFlag = true
      this.$set(this.list, index, row)
    },
    del(index) {
      this.list.splice(index, 1)
    }
  }
}
</script>

<style scoped>

</style>
