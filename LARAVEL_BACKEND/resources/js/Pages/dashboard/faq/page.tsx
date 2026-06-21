'use client'

import { useState, useCallback, useEffect, useRef } from 'react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { StatsCard, StatsGrid } from '@/components/shared/stats-card'
import { DataTable, type Column, type Filter } from '@/components/shared/data-table'
import { StatusBadge } from '@/components/shared/status-badge'
import { FormModal, ConfirmModal } from '@/components/shared/modal'
import { InputField, TextareaField, SelectField, SwitchField, TagInputField, type TagInputFieldHandle } from '@/components/shared/form-field'
import { useFAQs, useCompanySettings } from '@/lib/api-hooks'
import { createFAQ, updateFAQ, deleteFAQ, updateSettings, companyExportData, importFaqs } from '@/lib/api-actions'
import { downloadFile } from '@/lib/api-client'
import type { FAQ } from '@/lib/mock-data'
import {
  Plus,
  Edit,
  Trash2,
  HelpCircle,
  Bot,
  MessageSquare,
  Download,
  Upload,
  Loader2,
} from 'lucide-react'
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
  TooltipProvider,
} from '@/components/ui/tooltip'
import { useSWRConfig } from 'swr'

interface FAQFormData {
  question: string
  answer: string
  category: string
  keywords: string[]
}

interface BotSettings {
  greeting: string
  fallback: string
  away: string
  timezone: string
  workingHours: Record<string, string>
  autoReplyEnabled: boolean
  humanHandoff: boolean
  learnFromConversations: boolean
}

const initialFormData: FAQFormData = {
  question: '',
  answer: '',
  category: '',
  keywords: [],
}

const initialBotSettings: BotSettings = {
  greeting: 'Hello! Welcome to our store. How can I help you today?',
  fallback: "Thanks for your message. Our team will get back to you shortly.",
  away: "Thanks for your message! We're currently closed but will get back to you as soon as we open.",
  timezone: 'UTC',
  workingHours: {},
  autoReplyEnabled: true,
  humanHandoff: true,
  learnFromConversations: true,
}

export default function FAQAutomationPage() {
  const { mutate } = useSWRConfig()
  const [searchQuery, setSearchQuery] = useState('')
  const [categoryFilter, setCategoryFilter] = useState('all')
  
  // Modal states
  const [isAddModalOpen, setIsAddModalOpen] = useState(false)
  const [isEditModalOpen, setIsEditModalOpen] = useState(false)
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false)
  const [selectedFAQ, setSelectedFAQ] = useState<FAQ | null>(null)
  const [formData, setFormData] = useState<FAQFormData>(initialFormData)
  const [botSettings, setBotSettings] = useState<BotSettings>(initialBotSettings)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [formErrors, setFormErrors] = useState<Record<string, string>>({})
  const [exportOpen, setExportOpen] = useState(false)
  const [exportFormat, setExportFormat] = useState<'csv' | 'json'>('csv')
  const [exporting, setExporting] = useState(false)
  const [importing, setImporting] = useState(false)
  const [importResult, setImportResult] = useState<{ created: number; errors?: { row: number; errors: string[] }[] } | null>(null)
  const importInputRef = useRef<HTMLInputElement>(null)
  const faqKeywordsRef = useRef<TagInputFieldHandle>(null)

  const { data: companySettings } = useCompanySettings()

  // API: GET /api/company/faqs (useFAQs)
  const { data: faqs, isLoading, error } = useFAQs({
    category: categoryFilter,
    search: searchQuery,
  })

  useEffect(() => {
    if (companySettings) {
      setBotSettings((prev) => ({
        ...prev,
        greeting: companySettings.aiGreeting ?? prev.greeting,
        fallback: companySettings.fallbackMessage ?? prev.fallback,
        away: companySettings.awayMessage ?? prev.away,
        timezone: companySettings.timezone ?? prev.timezone,
        workingHours: companySettings.workingHours ?? prev.workingHours,
        learnFromConversations: companySettings.learnFromConversations ?? prev.learnFromConversations,
        autoReplyEnabled: companySettings.autoReplyEnabled ?? prev.autoReplyEnabled,
      }))
    }
  }, [companySettings])

  // Calculate stats from data
  const stats = {
    total: faqs?.length || 0,
    active: faqs?.filter((f) => f.isActive).length || 0,
    totalHits: faqs?.reduce((acc, f) => acc + f.usageCount, 0) || 0,
  }

  // Validate form (pass merged keywords when the tag input still has uncommitted text)
  const validateForm = (keywordsOverride?: string[]): boolean => {
    const errors: Record<string, string> = {}
    const keywords = keywordsOverride ?? formData.keywords

    if (!formData.question.trim()) {
      errors.question = 'Question is required'
    }
    if (!formData.answer.trim()) {
      errors.answer = 'Answer is required'
    }
    if (!formData.category) {
      errors.category = 'Category is required'
    }
    if (keywords.length === 0) {
      errors.keywords = 'At least one keyword is required'
    }

    setFormErrors(errors)
    return Object.keys(errors).length === 0
  }

  // Handle form field change
  const handleFieldChange = (field: keyof FAQFormData, value: string | string[]) => {
    setFormData((prev) => ({ ...prev, [field]: value }))
    if (formErrors[field]) {
      setFormErrors((prev) => ({ ...prev, [field]: '' }))
    }
  }

  // Handle bot settings change
  const handleSettingsChange = (field: keyof BotSettings, value: string | boolean | Record<string, string>) => {
    setBotSettings((prev) => ({ ...prev, [field]: value }))
  }

  // Handle create FAQ — api-actions.createFAQ → POST /api/company/faqs
  const handleCreateFAQ = useCallback(async () => {
    const keywords = faqKeywordsRef.current?.commitPending() ?? formData.keywords
    if (!validateForm(keywords)) return

    setIsSubmitting(true)
    try {
      const result = await createFAQ({
        question: formData.question,
        answer: formData.answer,
        category: formData.category,
        keywords,
      })

      if (result.success) {
        mutate(['faqs', { category: categoryFilter, search: searchQuery }])
        setIsAddModalOpen(false)
        setFormData(initialFormData)
      }
    } catch (error) {
      console.error('Failed to create FAQ:', error)
    } finally {
      setIsSubmitting(false)
    }
  }, [formData, mutate, categoryFilter, searchQuery])

  // Handle edit FAQ — api-actions.updateFAQ → PUT /api/company/faqs/:faqId
  const handleEditFAQ = useCallback(async () => {
    if (!selectedFAQ) return
    const keywords = faqKeywordsRef.current?.commitPending() ?? formData.keywords
    if (!validateForm(keywords)) return

    setIsSubmitting(true)
    try {
      const result = await updateFAQ(selectedFAQ.id, {
        question: formData.question,
        answer: formData.answer,
        category: formData.category,
        keywords,
      })

      if (result.success) {
        mutate(['faqs', { category: categoryFilter, search: searchQuery }])
        setIsEditModalOpen(false)
        setSelectedFAQ(null)
        setFormData(initialFormData)
      }
    } catch (error) {
      console.error('Failed to update FAQ:', error)
    } finally {
      setIsSubmitting(false)
    }
  }, [selectedFAQ, formData, mutate, categoryFilter, searchQuery])

  // Handle delete FAQ — api-actions.deleteFAQ → DELETE /api/company/faqs/:faqId
  const handleDeleteFAQ = useCallback(async () => {
    if (!selectedFAQ) return

    setIsSubmitting(true)
    try {
      const result = await deleteFAQ(selectedFAQ.id)

      if (result.success) {
        mutate(['faqs', { category: categoryFilter, search: searchQuery }])
        setIsDeleteModalOpen(false)
        setSelectedFAQ(null)
      }
    } catch (error) {
      console.error('Failed to delete FAQ:', error)
    } finally {
      setIsSubmitting(false)
    }
  }, [selectedFAQ, mutate, categoryFilter, searchQuery])

  const handleExportFaqs = async () => {
    setExporting(true)
    try {
      const result = await companyExportData('faqs', exportFormat)
      if (result.success && result.downloadUrl && result.filename) {
        await downloadFile(result.downloadUrl, result.filename)
        setExportOpen(false)
      }
    } finally {
      setExporting(false)
    }
  }

  const handleImportFaqs = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file) return
    setImportResult(null)
    setImporting(true)
    try {
      const result = await importFaqs(file)
      if (result.success) {
        setImportResult({ created: result.created ?? 0, errors: result.errors })
        mutate(['faqs', { category: categoryFilter, search: searchQuery }])
      }
    } finally {
      setImporting(false)
    }
    e.target.value = ''
  }

  // Handle toggle FAQ active status — api-actions.updateFAQ (isActive) → PUT /api/company/faqs/:faqId
  const handleToggleFAQStatus = useCallback(async (faq: FAQ) => {
    try {
      const result = await updateFAQ(faq.id, { isActive: !faq.isActive })
      if (result.success) {
        mutate(['faqs', { category: categoryFilter, search: searchQuery }])
      }
    } catch (error) {
      console.error('Failed to toggle FAQ status:', error)
    }
  }, [mutate, categoryFilter, searchQuery])

  // Handle save bot settings — api-actions.updateSettings → PUT /api/company/settings
  const handleSaveSettings = useCallback(async () => {
    setIsSubmitting(true)
    try {
      const result = await updateSettings({
        aiGreeting: botSettings.greeting,
        fallbackMessage: botSettings.fallback,
        awayMessage: botSettings.away,
        timezone: botSettings.timezone,
        workingHours: Object.keys(botSettings.workingHours).length ? botSettings.workingHours : undefined,
        learnFromConversations: botSettings.learnFromConversations,
        autoReplyEnabled: botSettings.autoReplyEnabled,
      })

      if (result.success) {
        // Show success notification
      }
    } catch (error) {
      console.error('Failed to save settings:', error)
    } finally {
      setIsSubmitting(false)
    }
  }, [botSettings])

  // Open edit modal with FAQ data
  const openEditModal = (faq: FAQ) => {
    setSelectedFAQ(faq)
    setFormData({
      question: faq.question,
      answer: faq.answer,
      category: faq.category,
      keywords: faq.keywords,
    })
    setFormErrors({})
    setIsEditModalOpen(true)
  }

  // Table columns definition
  const columns: Column<FAQ>[] = [
    {
      key: 'question',
      header: 'Question',
      cell: (faq) => (
        <div className="max-w-md">
          <p className="font-medium text-foreground">{faq.question}</p>
          <p className="text-sm text-muted-foreground truncate">{faq.answer}</p>
        </div>
      ),
    },
    {
      key: 'category',
      header: 'Category',
      cell: (faq) => (
        <Badge variant="outline" className="border-border/50">
          {faq.category}
        </Badge>
      ),
    },
    {
      key: 'keywords',
      header: 'Keywords',
      cell: (faq) => (
        <div className="flex flex-wrap gap-1">
          {faq.keywords.slice(0, 3).map((keyword) => (
            <Badge key={keyword} variant="secondary" className="text-xs">
              {keyword}
            </Badge>
          ))}
          {faq.keywords.length > 3 && (
            <Badge variant="outline" className="text-xs border-border/50">
              +{faq.keywords.length - 3}
            </Badge>
          )}
        </div>
      ),
    },
    {
      key: 'usageCount',
      header: 'Hits',
      cell: (faq) => <span className="text-foreground">{faq.usageCount}</span>,
    },
    {
      key: 'status',
      header: 'Status',
      cell: (faq) => (
        <StatusBadge status={faq.isActive ? 'active' : 'inactive'} />
      ),
    },
    {
      key: 'actions',
      header: '',
      cell: (faq) => (
        <div className="flex items-center gap-2">
          <Button variant="ghost" size="icon" onClick={() => openEditModal(faq)}>
            <Edit className="h-4 w-4" />
          </Button>
          <Button
            variant="ghost"
            size="icon"
            onClick={() => {
              setSelectedFAQ(faq)
              setIsDeleteModalOpen(true)
            }}
          >
            <Trash2 className="h-4 w-4" />
          </Button>
        </div>
      ),
    },
  ]

  // Filter options
  const filters: Filter[] = [
    {
      key: 'category',
      label: 'Category',
      options: [
        { value: 'all', label: 'All Categories' },
        { value: 'Shipping', label: 'Shipping' },
        { value: 'Payment', label: 'Payment' },
        { value: 'Returns', label: 'Returns' },
        { value: 'Products', label: 'Products' },
        { value: 'General', label: 'General' },
      ],
    },
  ]

  // FAQ form fields
  const renderFAQForm = () => (
    <div className="space-y-4">
      <InputField
        label="Question"
        name="question"
        value={formData.question}
        onChange={(value) => handleFieldChange('question', value)}
        placeholder="Enter the question customers might ask"
        error={formErrors.question}
        required
      />

      <SelectField
        label="Category"
        name="category"
        value={formData.category}
        onChange={(value) => handleFieldChange('category', value)}
        options={[
          { value: 'Shipping', label: 'Shipping' },
          { value: 'Payment', label: 'Payment' },
          { value: 'Returns', label: 'Returns' },
          { value: 'Products', label: 'Products' },
          { value: 'General', label: 'General' },
        ]}
        placeholder="Select category"
        error={formErrors.category}
        required
      />

      <TagInputField
        ref={faqKeywordsRef}
        label="Keywords"
        name="keywords"
        value={formData.keywords}
        onChange={(value) => handleFieldChange('keywords', value)}
        placeholder="Type a keyword, press Enter — or save (adds automatically)"
        description="Keywords help AI match this FAQ to customer questions"
        error={formErrors.keywords}
        required
      />

      <TextareaField
        label="Answer"
        name="answer"
        value={formData.answer}
        onChange={(value) => handleFieldChange('answer', value)}
        placeholder="Enter the automated response"
        rows={4}
        error={formErrors.answer}
        required
      />
    </div>
  )

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex flex-col gap-2">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-foreground">FAQ Automation</h1>
            <p className="text-muted-foreground">
              Configure AI responses for common questions
            </p>
          </div>
          <div className="flex items-center gap-2">
            <TooltipProvider>
              <Tooltip>
                <TooltipTrigger asChild>
                  <Popover open={exportOpen} onOpenChange={setExportOpen}>
                    <PopoverTrigger asChild>
                      <Button variant="outline" size="sm">
                        <Download className="mr-2 h-4 w-4" />
                        Export
                      </Button>
                    </PopoverTrigger>
                    <PopoverContent className="w-64" align="end">
                      <div className="space-y-3">
                        <p className="text-sm font-medium">Export FAQs</p>
                        <Select value={exportFormat} onValueChange={(v) => setExportFormat(v as 'csv' | 'json')}>
                          <SelectTrigger><SelectValue /></SelectTrigger>
                          <SelectContent>
                            <SelectItem value="csv">CSV (Excel)</SelectItem>
                            <SelectItem value="json">JSON</SelectItem>
                          </SelectContent>
                        </Select>
                        <Button size="sm" className="w-full" onClick={handleExportFaqs} disabled={exporting}>
                          {exporting ? <Loader2 className="h-4 w-4 animate-spin mr-2" /> : <Download className="h-4 w-4 mr-2" />}
                          {exporting ? 'Exporting…' : 'Download'}
                        </Button>
                      </div>
                    </PopoverContent>
                  </Popover>
                </TooltipTrigger>
                <TooltipContent side="bottom" className="max-w-xs">
                  Download your FAQs as CSV or JSON for backup or editing.
                </TooltipContent>
              </Tooltip>
              <Tooltip>
                <TooltipTrigger asChild>
                  <span>
                    <input
                      type="file"
                      accept=".csv,.txt"
                      className="hidden"
                      ref={importInputRef}
                      onChange={handleImportFaqs}
                    />
                    <Button
                      variant="outline"
                      size="sm"
                      disabled={importing}
                      onClick={() => importInputRef.current?.click()}
                    >
                      {importing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Upload className="mr-2 h-4 w-4" />}
                      {importing ? 'Importing…' : 'Import CSV'}
                    </Button>
                  </span>
                </TooltipTrigger>
                <TooltipContent side="bottom" className="max-w-xs">
                  Upload a CSV with columns: question, answer, category. Optional: keywords (comma-separated), is_active.
                </TooltipContent>
              </Tooltip>
            </TooltipProvider>
            <Button variant="outline" size="sm" asChild>
              <a href="/sample-data/faqs_sample.csv" download="faqs_sample.csv">Sample CSV</a>
            </Button>
            <Button onClick={() => {
              setFormData(initialFormData)
              setFormErrors({})
              setIsAddModalOpen(true)
            }}>
              <Plus className="mr-2 h-4 w-4" />
              Add FAQ
            </Button>
          </div>
        </div>
        {importResult !== null && (
          <p className="text-sm text-muted-foreground">
            Imported {importResult.created} FAQ(s).
            {importResult.errors?.length ? ` ${importResult.errors.length} row(s) had errors.` : ''}
          </p>
        )}
      </div>

      <Tabs defaultValue="faqs" className="space-y-6">
        <TabsList>
          <TabsTrigger value="faqs">FAQ Responses</TabsTrigger>
          <TabsTrigger value="settings">Bot Settings</TabsTrigger>
        </TabsList>

        <TabsContent value="faqs" className="space-y-6">
          {/* Stats Grid - API Ready */}
          <StatsGrid columns={3}>
            <StatsCard
              title="Total FAQs"
              value={stats.total}
              icon={HelpCircle}
              isLoading={isLoading}
            />
            <StatsCard
              title="Active FAQs"
              value={stats.active}
              icon={Bot}
              isLoading={isLoading}
            />
            <StatsCard
              title="Total Hits"
              value={stats.totalHits}
              icon={MessageSquare}
              isLoading={isLoading}
              formatter={(v) => v.toLocaleString()}
            />
          </StatsGrid>

          {/* FAQs Table - API Ready */}
          <Card className="bg-card border-border/50">
            <CardHeader>
              <CardTitle className="text-base font-medium">FAQ Responses</CardTitle>
            </CardHeader>
            <CardContent>
              <DataTable
                data={faqs}
                columns={columns}
                isLoading={isLoading}
                error={error}
                searchPlaceholder="Search FAQs..."
                onSearch={setSearchQuery}
                filters={filters}
                filterValues={{ category: categoryFilter }}
                onFilterChange={(key, value) => {
                  if (key === 'category') setCategoryFilter(value)
                }}
                emptyMessage="No FAQs found"
                emptyDescription="Add FAQs to automate responses to common questions"
              />
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="settings" className="space-y-6">
          <Card className="bg-card border-border/50">
            <CardHeader>
              <CardTitle>Bot Configuration</CardTitle>
              <CardDescription>
                Configure how your AI bot responds to customers
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <TextareaField
                label="Greeting Message"
                name="greeting"
                value={botSettings.greeting}
                onChange={(value) => handleSettingsChange('greeting', value)}
                rows={3}
                description="First message sent when a customer starts a conversation"
              />

              <TextareaField
                label="Fallback Message"
                name="fallback"
                value={botSettings.fallback}
                onChange={(value) => handleSettingsChange('fallback', value)}
                rows={3}
                description="Message sent when AI cannot understand the question"
              />

              <TextareaField
                label="Away Message"
                name="away"
                value={botSettings.away}
                onChange={(value) => handleSettingsChange('away', value)}
                rows={3}
                description="Message sent outside of business hours"
              />

              <InputField
                label="Timezone"
                name="timezone"
                value={botSettings.timezone}
                onChange={(value) => handleSettingsChange('timezone', value)}
                description="e.g. UTC, Africa/Nairobi, America/New_York (for away message hours)"
              />

              <div className="space-y-4 border-t border-border/50 pt-4">
                <SwitchField
                  label="Auto-reply enabled"
                  description="AI will automatically respond to messages"
                  checked={botSettings.autoReplyEnabled}
                  onCheckedChange={(checked) =>
                    handleSettingsChange('autoReplyEnabled', checked)
                  }
                />

                <SwitchField
                  label="Human handoff"
                  description="Transfer complex queries to human agents"
                  checked={botSettings.humanHandoff}
                  onCheckedChange={(checked) =>
                    handleSettingsChange('humanHandoff', checked)
                  }
                />

                <SwitchField
                  label="Learn from conversations"
                  description="AI improves responses based on interactions"
                  checked={botSettings.learnFromConversations}
                  onCheckedChange={(checked) =>
                    handleSettingsChange('learnFromConversations', checked)
                  }
                />
              </div>

              <Button onClick={handleSaveSettings} disabled={isSubmitting}>
                {isSubmitting ? 'Saving...' : 'Save Settings'}
              </Button>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      {/* Add FAQ Modal */}
      <FormModal
        open={isAddModalOpen}
        onOpenChange={setIsAddModalOpen}
        title="Add New FAQ"
        description="Add a new automated response"
        onSubmit={handleCreateFAQ}
        submitLabel="Add FAQ"
        isLoading={isSubmitting}
        isValid={formData.question.trim() !== '' && formData.answer.trim() !== ''}
      >
        {renderFAQForm()}
      </FormModal>

      {/* Edit FAQ Modal */}
      <FormModal
        open={isEditModalOpen}
        onOpenChange={(open) => {
          if (!open) {
            setSelectedFAQ(null)
            setFormData(initialFormData)
          }
          setIsEditModalOpen(open)
        }}
        title="Edit FAQ"
        description="Update FAQ details"
        onSubmit={handleEditFAQ}
        submitLabel="Save Changes"
        isLoading={isSubmitting}
        isValid={formData.question.trim() !== '' && formData.answer.trim() !== ''}
      >
        {renderFAQForm()}
      </FormModal>

      {/* Delete Confirmation Modal */}
      <ConfirmModal
        open={isDeleteModalOpen}
        onOpenChange={(open) => {
          if (!open) setSelectedFAQ(null)
          setIsDeleteModalOpen(open)
        }}
        title="Delete FAQ"
        description={`Are you sure you want to delete this FAQ? This action cannot be undone.`}
        confirmLabel="Delete"
        onConfirm={handleDeleteFAQ}
        isLoading={isSubmitting}
        variant="destructive"
      />
    </div>
  )
}
