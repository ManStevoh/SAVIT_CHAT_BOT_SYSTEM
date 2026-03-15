"use client"

import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { Switch } from "@/components/ui/switch"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Settings, Shield, Mail, Bell, Database, Globe } from "lucide-react"

export default function AdminSettingsPage() {
  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-foreground">Platform Settings</h1>
        <p className="text-muted-foreground">Configure platform-wide settings</p>
      </div>

      <Tabs defaultValue="general" className="space-y-6">
        <TabsList>
          <TabsTrigger value="general" className="gap-2">
            <Settings className="h-4 w-4" />
            General
          </TabsTrigger>
          <TabsTrigger value="security" className="gap-2">
            <Shield className="h-4 w-4" />
            Security
          </TabsTrigger>
          <TabsTrigger value="email" className="gap-2">
            <Mail className="h-4 w-4" />
            Email
          </TabsTrigger>
          <TabsTrigger value="notifications" className="gap-2">
            <Bell className="h-4 w-4" />
            Notifications
          </TabsTrigger>
        </TabsList>

        <TabsContent value="general">
          <Card>
            <CardHeader>
              <CardTitle>General Settings</CardTitle>
              <CardDescription>Platform-wide configuration options</CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <FieldGroup>
                <Field>
                  <FieldLabel htmlFor="platformName">Platform Name</FieldLabel>
                  <Input id="platformName" defaultValue="ChatFlow AI" />
                </Field>

                <Field>
                  <FieldLabel htmlFor="supportEmail">Support Email</FieldLabel>
                  <Input id="supportEmail" type="email" defaultValue="support@chatflow.ai" />
                </Field>

                <Field>
                  <FieldLabel htmlFor="defaultTimezone">Default Timezone</FieldLabel>
                  <Select defaultValue="utc">
                    <SelectTrigger>
                      <SelectValue placeholder="Select timezone" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="utc">UTC</SelectItem>
                      <SelectItem value="est">Eastern Time (EST)</SelectItem>
                      <SelectItem value="pst">Pacific Time (PST)</SelectItem>
                      <SelectItem value="gmt">GMT</SelectItem>
                    </SelectContent>
                  </Select>
                </Field>

                <Field>
                  <FieldLabel htmlFor="maintenanceMessage">Maintenance Message</FieldLabel>
                  <Textarea 
                    id="maintenanceMessage" 
                    placeholder="Message to show during maintenance" 
                    rows={3}
                  />
                </Field>
              </FieldGroup>

              <div className="space-y-4 pt-4 border-t border-border">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-foreground">Maintenance Mode</p>
                    <p className="text-sm text-muted-foreground">Disable access for all users except admins</p>
                  </div>
                  <Switch />
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-foreground">New Registrations</p>
                    <p className="text-sm text-muted-foreground">Allow new companies to register</p>
                  </div>
                  <Switch defaultChecked />
                </div>
              </div>

              <Button>Save Settings</Button>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="security">
          <Card>
            <CardHeader>
              <CardTitle>Security Settings</CardTitle>
              <CardDescription>Configure security and authentication options</CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <FieldGroup>
                <Field>
                  <FieldLabel htmlFor="sessionTimeout">Session Timeout (minutes)</FieldLabel>
                  <Input id="sessionTimeout" type="number" defaultValue="60" />
                </Field>

                <Field>
                  <FieldLabel htmlFor="maxLoginAttempts">Max Login Attempts</FieldLabel>
                  <Input id="maxLoginAttempts" type="number" defaultValue="5" />
                </Field>

                <Field>
                  <FieldLabel htmlFor="passwordMinLength">Minimum Password Length</FieldLabel>
                  <Input id="passwordMinLength" type="number" defaultValue="8" />
                </Field>
              </FieldGroup>

              <div className="space-y-4 pt-4 border-t border-border">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-foreground">Two-Factor Authentication</p>
                    <p className="text-sm text-muted-foreground">Require 2FA for all admin accounts</p>
                  </div>
                  <Switch defaultChecked />
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-foreground">IP Allowlisting</p>
                    <p className="text-sm text-muted-foreground">Restrict admin access to specific IPs</p>
                  </div>
                  <Switch />
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-foreground">Audit Logging</p>
                    <p className="text-sm text-muted-foreground">Log all admin actions</p>
                  </div>
                  <Switch defaultChecked />
                </div>
              </div>

              <Button>Save Security Settings</Button>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="email">
          <Card>
            <CardHeader>
              <CardTitle>Email Settings</CardTitle>
              <CardDescription>Configure email delivery settings</CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <FieldGroup>
                <Field>
                  <FieldLabel htmlFor="smtpHost">SMTP Host</FieldLabel>
                  <Input id="smtpHost" defaultValue="smtp.sendgrid.net" />
                </Field>

                <div className="grid gap-4 md:grid-cols-2">
                  <Field>
                    <FieldLabel htmlFor="smtpPort">SMTP Port</FieldLabel>
                    <Input id="smtpPort" defaultValue="587" />
                  </Field>
                  <Field>
                    <FieldLabel htmlFor="smtpEncryption">Encryption</FieldLabel>
                    <Select defaultValue="tls">
                      <SelectTrigger>
                        <SelectValue placeholder="Select encryption" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="none">None</SelectItem>
                        <SelectItem value="ssl">SSL</SelectItem>
                        <SelectItem value="tls">TLS</SelectItem>
                      </SelectContent>
                    </Select>
                  </Field>
                </div>

                <Field>
                  <FieldLabel htmlFor="smtpUser">SMTP Username</FieldLabel>
                  <Input id="smtpUser" defaultValue="apikey" />
                </Field>

                <Field>
                  <FieldLabel htmlFor="smtpPassword">SMTP Password</FieldLabel>
                  <Input id="smtpPassword" type="password" defaultValue="••••••••••••" />
                </Field>

                <Field>
                  <FieldLabel htmlFor="fromEmail">From Email</FieldLabel>
                  <Input id="fromEmail" type="email" defaultValue="noreply@chatflow.ai" />
                </Field>
              </FieldGroup>

              <div className="flex gap-2">
                <Button>Save Email Settings</Button>
                <Button variant="outline">Send Test Email</Button>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="notifications">
          <Card>
            <CardHeader>
              <CardTitle>Admin Notifications</CardTitle>
              <CardDescription>Configure admin notification preferences</CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-foreground">New company registrations</p>
                    <p className="text-sm text-muted-foreground">Notify when new companies sign up</p>
                  </div>
                  <Switch defaultChecked />
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-foreground">Failed payments</p>
                    <p className="text-sm text-muted-foreground">Alert on subscription payment failures</p>
                  </div>
                  <Switch defaultChecked />
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-foreground">Security alerts</p>
                    <p className="text-sm text-muted-foreground">Suspicious login attempts and security events</p>
                  </div>
                  <Switch defaultChecked />
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-foreground">System errors</p>
                    <p className="text-sm text-muted-foreground">Critical system errors and failures</p>
                  </div>
                  <Switch defaultChecked />
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-foreground">Usage alerts</p>
                    <p className="text-sm text-muted-foreground">When companies approach usage limits</p>
                  </div>
                  <Switch defaultChecked />
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-foreground">Daily summary</p>
                    <p className="text-sm text-muted-foreground">Receive daily platform activity summary</p>
                  </div>
                  <Switch defaultChecked />
                </div>
              </div>

              <Button>Save Notification Settings</Button>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  )
}
