<template>
  <div class="app-container">
    <div class="filter-container">
      <el-row>
        <el-col :span="6">
          <el-select v-model="search.target" placeholder="选择指标">
            <el-option v-for="row in targetList" :key="row.key" :label="row.val" :value="row.key"></el-option>
          </el-select>
        </el-col>
        <el-col :span="6">
          <el-select v-model="search.app_code" placeholder="选择应用" clearable filterable multiple>
            <el-option v-for="row in appsData" :key="row.app_code" :label="row.app_name" :value="row.app_code"></el-option>
          </el-select>
        </el-col>
        <el-col :span="6">
          <el-select v-model="search.platform" placeholder="选择投放渠道" clearable>
            <el-option v-for="(val, key) in source_map" :key="key" :label="val" :value="key"></el-option>
          </el-select>
        </el-col>
        <el-col :span="6">
          <el-select v-model="search.launch_type" placeholder="选择投放方式" clearable>
            <el-option v-for="(val, key) in launch_type_map" :key="key" :label="val" :value="key"></el-option>
          </el-select>
        </el-col>
      </el-row>
      <el-divider></el-divider>
      <el-row>
        <el-col :span="8">
          <el-date-picker
            v-model="searchDateA"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            :clearable="false"
          ></el-date-picker>
        </el-col>
        <el-col :span="8">
          <el-date-picker
            v-model="searchDateB"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            :clearable="false"
          ></el-date-picker>
        </el-col>
        <el-col :span="5">
          <el-button class="filter-item" type="primary" icon="el-icon-search" @click="fetchQuery" style="float:right;">
            搜索
          </el-button>
        </el-col>
      </el-row>
    </div>
    <div id="hour-chart" style="height:400px;width:100%;margin-top: 20px;"/>
  </div>
</template>

<script>
import { getAppList } from '@/utils/auth'
import { parseTime } from '@/utils'
import echarts from 'echarts'
import { launchHourChart } from '@/api/launch/lad'

export default {
  name: 'HourChart',
  created() {
    this.fetchDate()
    this.fetchQuery()
  },
  data() {
    return {
      appsData: getAppList(),
      source_map: this.$global.SOURCE_MAP,
      launch_type_map: this.$global.LAUNCH_TYPE_MAP,
      targetList: [
        { key: 'cost', val: '消耗' },
        { key: 'active', val: '激活' },
        { key: 'active_cost', val: '激活成本' },
        { key: 'key_action', val: '关键行为' },
        { key: 'key_action_cost', val: '关键行为成本' },
        { key: 'key_action_rate', val: '关键行为率' },
        { key: 'installed', val: '新增安装' },
        { key: 'installed_roi', val: '新增安装 ROI' }
      ],
      searchDateA: undefined,
      searchDateB: undefined,
      search: {
        app_code: undefined,
        launch_type: undefined,
        platform: undefined,
        target: 'cost',
        date_begin_a: undefined,
        date_end_a: undefined,
        date_begin_b: undefined,
        date_end_b: undefined
      },
      xAxisName: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
      aLine: {
        name: 'a',
        data: [80, 100, 121, 104, 105, 90, 100]
      },
      bLine: {
        name: 'b',
        data: [120, 90, 100, 138, 142, 130, 130]
      }
    }
  },
  methods: {
    fetchDate() {
      const aDate = new Date()
      const bDate = new Date()
      aDate.setTime(aDate.getTime())
      bDate.setTime(bDate.getTime() - 24 * 60 * 60 * 1000)
      this.searchDateA = [aDate, aDate]
      this.searchDateB = [bDate, bDate]
    },
    fetchQuery() {
      this.search.date_begin_a = parseTime(this.searchDateA[0], '{y}-{m}-{d}')
      this.search.date_end_a = parseTime(this.searchDateA[1], '{y}-{m}-{d}')
      this.search.date_begin_b = parseTime(this.searchDateB[0], '{y}-{m}-{d}')
      this.search.date_end_b = parseTime(this.searchDateB[1], '{y}-{m}-{d}')
      launchHourChart(this.search).then(response => {
        this.xAxisName = response.data.x_axis
        this.aLine = response.data.a_line
        this.bLine = response.data.b_line
        this.initChart()
      })
    },
    initChart() {
      this.chart = echarts.init(document.getElementById('hour-chart'))
      this.chart.setOption({
        xAxis: {
          data: this.xAxisName,
          boundaryGap: false,
          axisTick: {
            show: false
          }
        },
        grid: {
          left: 10,
          right: 10,
          bottom: 20,
          top: 30,
          containLabel: true
        },
        tooltip: {
          trigger: 'axis',
          axisPointer: {
            type: 'cross'
          },
          padding: [5, 10]
        },
        yAxis: {
          axisTick: {
            show: false
          }
        },
        legend: {
          data: [this.aLine.name, this.bLine.name]
        },
        series: [
          {
            name: this.aLine.name, itemStyle: {
              normal: {
                color: '#FF005A',
                lineStyle: {
                  color: '#FF005A',
                  width: 2
                }
              }
            },
            smooth: true,
            type: 'line',
            data: this.aLine.data,
            animationDuration: 2800,
            animationEasing: 'cubicInOut'
          },
          {
            name: this.bLine.name,
            smooth: true,
            type: 'line',
            itemStyle: {
              normal: {
                color: '#3888fa',
                lineStyle: {
                  color: '#3888fa',
                  width: 2
                },
                areaStyle: {
                  color: '#f3f8ff'
                }
              }
            },
            data: this.bLine.data,
            animationDuration: 2800,
            animationEasing: 'quadraticOut'
          }
        ]
      })
    }
  }
}
</script>

<style scoped>

</style>
