<?php
$navigator_json = '
[

    {
       "caption":"Human Resource",
       "id":"human-resource",
       "icon":"fas fa-database",
       "type":"folder",
       "permitted" : 0,
       "nodes":[
       {
          "caption": "Factories",
          "icon": "fas fa-industry",
          "type": "node",
          "permitted": 0,
          "path": "/ie/factories"
        },
        {
            "caption":"Departments",
            "icon":"fas fa-sitemap",
            "type":"node",
            "permitted" : 0,
            "path":"/ie/departments"
         },
        {
          "caption": "Teams",
          "icon": "fas fa-layer-group",
          "type": "node",
          "permitted": 0,
          "path": "/ie/production-lines"
        },
         {
            "caption":"Employees",
            "icon":"fas fa-users",
            "type":"node",
            "permitted" : 0,
            "path":"/ie/employees"
         }
       ]
    },
    {
       "caption":"Industrial Engineering",
       "id":"industrial-engineering",
       "icon":"fas fa-database",
       "type":"folder",
       "permitted" : 0,
       "nodes":[
         {
          "caption":"Products Master",
          "icon":"fas fa-shirt",
          "type":"node",
          "permitted" : 0,
          "path":"/ie/product-masters"
         },
         {
          "caption":"Customers & Related",
          "icon":"fas fa-handshake",
          "type":"node",
          "permitted" : 0,
          "path":"/ie/customer-and-related"
         },
        {
          "caption":"Machines",
          "icon":"fas fa-cogs",
          "type":"node",
          "permitted" : 0,
          "path":"/ie/machines"
        },
        {
          "caption":"Base Operations & Soft Skills",
          "icon":"fas fa-list-check",
          "type":"node",
          "permitted" : 0,
          "path":"/ie/op-skills"
        },
        {
          "caption":"Operations Master",
          "icon":"fas fa-diagram-project",
          "type":"node",
          "permitted" : 0,
          "path":"/ie/operation-grading"
        },
        {
          "caption":"Products",
          "icon":"fas fa-shirt",
          "type":"node",
          "permitted" : 0,
          "path":"/ie/products"
        }
       ]
    },
    {
      "caption": "Time Study",
      "id": "time-study",
      "icon": "fas fa-layer-group",
      "type": "folder",
      "permitted" : 0,
      "nodes": [
        {
          "caption":"Time Study",
          "icon":"fas fa-stopwatch",
          "type":"node",
          "permitted" : 0,
          "path":"/ts/time-study"
        },
        {
          "caption":"Skill Matrix",
          "icon":"fas fa-table-cells",
          "type":"node",
          "permitted" : 0,
          "path":"/ts/skill-matrix"
        },
        {
          "caption":"Time Study Reports",
          "icon":"fas fa-chart-column",
          "type":"node",
          "permitted" : 0,
          "path":"/ts/time-study-report"
        },
        {
          "caption":"Time Study Downtime Reasons",
          "icon":"fas fa-triangle-exclamation",
          "type":"node",
          "permitted" : 0,
          "path":"/ts/time-study-downtime-reasons"
        }
      ]
    },
    {
      "caption": "Planning",
      "id": "planning",
      "icon": "fas fa-layer-group",
      "type": "folder",
      "permitted" : 0,
      "nodes": [
        {
          "caption":"Team Plan",
          "icon":"fas fa-chart-gantt",
          "type":"node",
          "permitted" : 0,
          "path":"/ie/team-plan"
        }
      ]
    },
    {
      "caption": "User Management",
      "id": "user-management",
      "icon": "fas fa-user-tie",
      "type": "folder",
      "permitted" : 0,
      "nodes": [
        {
          "caption": "Users",
          "icon": "fas fa-user-plus",
          "type": "node",
          "permitted" : 0,
          "path": "/createUser"
        },
        {
          "caption": "User Roles",
          "icon": "fas fa-user-tag",
          "type": "node",
          "permitted" : 0,
          "path": "/userRoles"
        },
        {
          "caption": "Permissions",
          "icon": "fas fa-user-lock",
          "type": "node",
          "permitted" : 0,
          "path": "/permissions"
        }
      ]
    }



 ]
  ';
