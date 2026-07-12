<?php
$navigator_json = '
[

    {
       "caption":"Master Data",
       "id":"master-data",
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
  "caption": "Teams",
  "icon": "fas fa-layer-group",
  "type": "node",
  "permitted": 0,
  "path": "/ie/production-lines"
},

         {
            "caption":"Departments",
            "icon":"fas fa-sitemap",
            "type":"node",
            "permitted" : 0,
            "path":"/ie/departments"
         },
         {
            "caption":"Employees",
            "icon":"fas fa-users",
            "type":"node",
            "permitted" : 0,
            "path":"/ie/employees"
         },
         {
          "caption":"Products",
          "icon":"fas fa-shirt",
          "type":"node",
          "permitted" : 0,
          "path":"/ie/products"
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
        }
       ]
    },
    {
      "caption": "Product Management",
      "id": "product-management",
      "icon": "fas fa-layer-group",
      "type": "folder",
      "permitted" : 0,
      "nodes": [
        {
          "caption":"Line Schedule",
          "icon":"fas fa-chart-gantt",
          "type":"node",
          "permitted" : 0,
          "path":"/ie/line-schedule"
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
