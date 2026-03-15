"use client"

import { useState, useEffect } from "react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { Switch } from "@/components/ui/switch"
import { Badge } from "@/components/ui/badge"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import { Building2, MessageSquare, Bot, Users, Bell, Plus, Trash2, Check } from "lucide-react"
// API: GET /api/company/settings (useCompanySettings), PUT /api/company/settings (updateSettings)
import { useCompanySettings } from "@/lib/api-hooks"
import { updateSettings } from "@/lib/api-actions"

// Team members: API GET /api/company/team — replace with useCompanyTeam() when available
const teamMembersPlaceholder = [
  { id: 1, name: "John Doe", email: "john@company.com", role: "Admin", status: "active" },
  { id: 2, name: "Jane Smith", email: "jane@company.com", role: "Agent", status: "active" },
  { id: 3, name: "Bob Wilson", email: "bob@company.com", role: "Agent", status: "active" },
  { id: 4, name: "Alice Brown", email: "alice@company.com", role: "Viewer", status: "pending" },
]

// WhatsApp numbers: API GET /api/company/whatsapp/numbers — replace with hook when available
const whatsappNumbersPlaceholder = [
  { id: 1, number: "+1 555-0123", name: "Main Business", status: "connected" },
  { id: 2, number: "+1 555-0124", name: "Support Line", status: "connected" },
]

export default function SettingsPage() {
  const { data: settings } = useCompanySettings()
  const [activeTab, setActiveTab] = useState("profile")
  const [profileSaving, setProfileSaving] = useState(false)
  const [profileError, setProfileError] = useState<string | null>(null)
  const [profileSuccess, setProfileSuccess] = useState(false)
  const [businessName, setBusinessName] = useState("QuickBite Restaurant")
  const [email, setEmail] = useState("contact@quickbite.com")
  const [phone, setPhone] = useState("+1 555-0100")
  const [address, setAddress] = useState("123 Main Street, New York, NY 10001")

  // Load initial values from GET /api/company/settings when available
  useEffect(() => {
    if (settings) {
      if (settings.companyName != null) setBusinessName(settings.companyName)
      if (settings.email != null) setEmail(settings.email)
      if (settings.phone != null) setPhone(settings.phone)
      if (settings.address != null) setAddress(settings.address)
    }
  }, [settings])

  const handleProfileSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    setProfileError(null)
    setProfileSuccess(false)
    setProfileSaving(true)
    const result = await updateSettings({
      companyName: businessName,
      email,
      phone,
    })
    setProfileSaving(false)
    if (!result.success) {
      setProfileError(result.message ?? "Failed to save")
      return
    }
    setProfileSuccess(true)
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-foreground">Settings</h1>
        <p className="text-muted-foreground">Manage your account and preferences</p>
      </div>

      <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-6">
        <TabsList className="flex-wrap h-auto gap-2">
          <TabsTrigger value="profile" className="gap-2">
            <Building2 className="h-4 w-4" />
            Business Profile
          </TabsTrigger>
          <TabsTrigger value="whatsapp" className="gap-2">
            <MessageSquare className="h-4 w-4" />
            WhatsApp Setup
          </TabsTrigger>
          <TabsTrigger value="ai" className="gap-2">
            <Bot className="h-4 w-4" />
            AI Settings
          </TabsTrigger>
          <TabsTrigger value="team" className="gap-2">
            <Users className="h-4 w-4" />
            Staff Management
          </TabsTrigger>
          <TabsTrigger value="notifications" className="gap-2">
            <Bell className="h-4 w-4" />
            Notifications
          </TabsTrigger>
        </TabsList>

        {/* Business Profile — API: PUT /api/company/settings (companyName, email, phone) */}
        <TabsContent value="profile">
          <Card>
            <CardHeader>
              <CardTitle>Business Profile</CardTitle>
              <CardDescription>Update your business information</CardDescription>
            </CardHeader>
            <CardContent>
              <form className="space-y-6" onSubmit={handleProfileSubmit}>
                {profileError && (
                  <div className="rounded-lg border border-destructive/50 bg-destructive/10 px-4 py-2 text-sm text-destructive">
                    {profileError}
                  </div>
                )}
                {profileSuccess && (
                  <div className="rounded-lg border border-primary/50 bg-primary/10 px-4 py-2 text-sm text-primary">
                    Settings saved successfully.
                  </div>
                )}
                <FieldGroup>
                  <Field>
                    <FieldLabel htmlFor="businessName">Business Name</FieldLabel>
                    <Input id="businessName" value={businessName} onChange={(e) => setBusinessName(e.target.value)} />
                  </Field>

                  <div className="grid gap-4 md:grid-cols-2">
                    <Field>
                      <FieldLabel htmlFor="email">Email</FieldLabel>
                      <Input id="email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} />
                    </Field>
                    <Field>
                      <FieldLabel htmlFor="phone">Phone</FieldLabel>
                      <Input id="phone" value={phone} onChange={(e) => setPhone(e.target.value)} />
                    </Field>
                  </div>

                  <Field>
                    <FieldLabel htmlFor="address">Address</FieldLabel>
                    <Textarea id="address" value={address} onChange={(e) => setAddress(e.target.value)} rows={2} />
                  </Field>

                  <Field>
                    <FieldLabel htmlFor="timezone">Timezone</FieldLabel>
                    <Select defaultValue="america-new-york">
                      <SelectTrigger>
                        <SelectValue placeholder="Select timezone" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="america-new-york">America/New York (EST)</SelectItem>
                        <SelectItem value="america-los-angeles">America/Los Angeles (PST)</SelectItem>
                        <SelectItem value="europe-london">Europe/London (GMT)</SelectItem>
                        <SelectItem value="asia-tokyo">Asia/Tokyo (JST)</SelectItem>
                      </SelectContent>
                    </Select>
                  </Field>
                </FieldGroup>

                <Button type="submit" disabled={profileSaving}>{profileSaving ? "Saving..." : "Save Changes"}</Button>
              </form>
            </CardContent>
          </Card>
        </TabsContent>

        {/* WhatsApp Setup */}
        <TabsContent value="whatsapp">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <div>
                <CardTitle>WhatsApp Numbers</CardTitle>
                <CardDescription>Manage your connected WhatsApp Business numbers</CardDescription>
              </div>
              <Button>
                <Plus className="h-4 w-4 mr-2" />
                Add Number
              </Button>
            </CardHeader>
            <CardContent>
              <div className="space-y-4">
                {whatsappNumbersPlaceholder.map((wa) => (
                  <div key={wa.id} className="flex items-center justify-between rounded-lg border border-border p-4">
                    <div className="flex items-center gap-4">
                      <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10">
                        <MessageSquare className="h-6 w-6 text-primary" />
                      </div>
                      <div>
                        <p className="font-medium text-foreground">{wa.name}</p>
                        <p className="text-sm text-muted-foreground">{wa.number}</p>
                      </div>
                    </div>
                    <div className="flex items-center gap-4">
                      <Badge variant="default" className="gap-1">
                        <Check className="h-3 w-3" />
                        {wa.status}
                      </Badge>
                      <Button variant="ghost" size="icon">
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        {/* AI Settings */}
        <TabsContent value="ai">
          <Card>
            <CardHeader>
              <CardTitle>AI Configuration</CardTitle>
              <CardDescription>Configure your AI assistant behavior</CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <Field>
                <FieldLabel htmlFor="aiModel">AI Model</FieldLabel>
                <Select defaultValue="gpt-4">
                  <SelectTrigger>
                    <SelectValue placeholder="Select AI model" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="gpt-4">GPT-4 (Recommended)</SelectItem>
                    <SelectItem value="gpt-3.5">GPT-3.5 Turbo</SelectItem>
                    <SelectItem value="claude">Claude 3</SelectItem>
                  </SelectContent>
                </Select>
              </Field>

              <Field>
                <FieldLabel htmlFor="personality">AI Personality</FieldLabel>
                <Textarea
                  id="personality"
                  defaultValue="You are a friendly and helpful customer service assistant for a restaurant. Be polite, professional, and helpful."
                  rows={3}
                />
              </Field>

              <Field>
                <FieldLabel htmlFor="responseStyle">Response Style</FieldLabel>
                <Select defaultValue="balanced">
                  <SelectTrigger>
                    <SelectValue placeholder="Select style" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="formal">Formal</SelectItem>
                    <SelectItem value="balanced">Balanced</SelectItem>
                    <SelectItem value="casual">Casual</SelectItem>
                  </SelectContent>
                </Select>
              </Field>

              <div className="space-y-4 pt-4 border-t border-border">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-foreground">Auto-suggest replies</p>
                    <p className="text-sm text-muted-foreground">AI suggests responses for human agents</p>
                  </div>
                  <Switch defaultChecked />
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-foreground">Sentiment analysis</p>
                    <p className="text-sm text-muted-foreground">Detect customer emotions</p>
                  </div>
                  <Switch defaultChecked />
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-foreground">Multi-language support</p>
                    <p className="text-sm text-muted-foreground">Respond in customer&apos;s language</p>
                  </div>
                  <Switch defaultChecked />
                </div>
              </div>

              <Button>Save AI Settings</Button>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Staff Management */}
        <TabsContent value="team">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <div>
                <CardTitle>Team Members</CardTitle>
                <CardDescription>Manage your team access and roles</CardDescription>
              </div>
              <Button>
                <Plus className="h-4 w-4 mr-2" />
                Invite Member
              </Button>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Member</TableHead>
                    <TableHead>Role</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead></TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {teamMembersPlaceholder.map((member) => (
                    <TableRow key={member.id}>
                      <TableCell>
                        <div className="flex items-center gap-3">
                          <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-sm font-medium text-primary">
                            {member.name.charAt(0)}
                          </div>
                          <div>
                            <div className="font-medium text-foreground">{member.name}</div>
                            <div className="text-sm text-muted-foreground">{member.email}</div>
                          </div>
                        </div>
                      </TableCell>
                      <TableCell>
                        <Select defaultValue={member.role.toLowerCase()}>
                          <SelectTrigger className="w-28">
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="admin">Admin</SelectItem>
                            <SelectItem value="agent">Agent</SelectItem>
                            <SelectItem value="viewer">Viewer</SelectItem>
                          </SelectContent>
                        </Select>
                      </TableCell>
                      <TableCell>
                        <Badge variant={member.status === "active" ? "default" : "secondary"}>
                          {member.status}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <Button variant="ghost" size="icon">
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Notifications */}
        <TabsContent value="notifications">
          <Card>
            <CardHeader>
              <CardTitle>Notification Preferences</CardTitle>
              <CardDescription>Configure how you receive notifications</CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="space-y-4">
                <h3 className="font-medium text-foreground">Email Notifications</h3>
                
                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-foreground">New orders</p>
                    <p className="text-sm text-muted-foreground">Get notified when a new order is placed</p>
                  </div>
                  <Switch defaultChecked />
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-foreground">AI handoff requests</p>
                    <p className="text-sm text-muted-foreground">When AI needs human assistance</p>
                  </div>
                  <Switch defaultChecked />
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-foreground">Daily summary</p>
                    <p className="text-sm text-muted-foreground">Receive a daily activity summary</p>
                  </div>
                  <Switch defaultChecked />
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-foreground">Weekly analytics</p>
                    <p className="text-sm text-muted-foreground">Receive weekly performance reports</p>
                  </div>
                  <Switch defaultChecked />
                </div>
              </div>

              <div className="space-y-4 pt-4 border-t border-border">
                <h3 className="font-medium text-foreground">Push Notifications</h3>
                
                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-foreground">New messages</p>
                    <p className="text-sm text-muted-foreground">Get push notifications for new messages</p>
                  </div>
                  <Switch defaultChecked />
                </div>

                <div className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-foreground">Order updates</p>
                    <p className="text-sm text-muted-foreground">Status changes on orders</p>
                  </div>
                  <Switch defaultChecked />
                </div>
              </div>

              <Button>Save Preferences</Button>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  )
}
